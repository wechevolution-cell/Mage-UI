<?php
declare(strict_types=1);

/**
 * Secure image uploads: JPG/PNG/WebP/GIF only, validated with getimagesize(),
 * max 10 MB, re-encoded through GD (strips any embedded payload), resized to max 2000px,
 * plus WebP responsive variants (400/800/1200). Files land in uploads/{shop}/Y/m/.
 */
final class ImageUpload
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_DIM = 2000;
    public const SIZES = [400, 800, 1200];
    private const MIMES = [IMAGETYPE_JPEG => ['image/jpeg', 'jpg'], IMAGETYPE_PNG => ['image/png', 'png'], IMAGETYPE_WEBP => ['image/webp', 'webp'], IMAGETYPE_GIF => ['image/gif', 'gif']];

    /** @return array{ok:bool,error?:string,media?:array} */
    public static function fromUpload(PDO $db, int $shopId, array $file, string $alt = ''): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE ? 'Το αρχείο είναι μεγαλύτερο από το επιτρεπόμενο όριο.' : 'Δεν ανέβηκε αρχείο.'];
        }
        if (!is_uploaded_file($file['tmp_name']) && PHP_SAPI !== 'cli') return ['ok' => false, 'error' => 'Μη έγκυρο αρχείο.'];
        return self::fromPath($db, $shopId, $file['tmp_name'], (string) ($file['name'] ?? 'image'), $alt);
    }

    /** @return array{ok:bool,error?:string,media?:array} */
    public static function fromPath(PDO $db, int $shopId, string $tmp, string $origName, string $alt = '', ?string $sourceUrl = null): array
    {
        $size = @filesize($tmp);
        if (!$size || $size > self::MAX_BYTES) return ['ok' => false, 'error' => 'Μέγιστο μέγεθος εικόνας 10 MB.'];
        $info = @getimagesize($tmp);
        if (!$info || !isset(self::MIMES[$info[2]])) return ['ok' => false, 'error' => 'Επιτρέπονται μόνο εικόνες JPG, PNG, WebP ή GIF.'];
        $finfo = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp) : self::MIMES[$info[2]][0];
        if ($finfo !== self::MIMES[$info[2]][0]) return ['ok' => false, 'error' => 'Ο τύπος αρχείου δεν αντιστοιχεί στην επέκταση.'];
        [$w, $h] = $info;
        if ($w < 1 || $h < 1 || $w * $h > 50_000_000) return ['ok' => false, 'error' => 'Μη έγκυρες διαστάσεις εικόνας.'];

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
            IMAGETYPE_PNG => @imagecreatefrompng($tmp),
            IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
            IMAGETYPE_GIF => @imagecreatefromgif($tmp),
        };
        if (!$src) return ['ok' => false, 'error' => 'Η εικόνα δεν μπόρεσε να διαβαστεί.'];
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmp);
            $o = (int) ($exif['Orientation'] ?? 1);
            if (in_array($o, [3, 6, 8], true)) {
                $src = imagerotate($src, [3 => 180, 6 => -90, 8 => 90][$o], 0);
                [$w, $h] = [imagesx($src), imagesy($src)];
            }
        }

        $base = slugify(pathinfo($origName, PATHINFO_FILENAME), 60) . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
        $rel = $shopId . '/' . date('Y/m');
        $dir = ensure_dir(UPLOAD_DIR . '/' . $rel);
        $ext = self::MIMES[$info[2]][1] === 'gif' ? 'gif' : (self::MIMES[$info[2]][1] === 'png' ? 'png' : (self::MIMES[$info[2]][1] === 'webp' ? 'webp' : 'jpg'));

        $main = self::resize($src, $w, $h, self::MAX_DIM);
        [$mw, $mh] = [imagesx($main), imagesy($main)];
        $mainPath = "$dir/$base.$ext";
        self::save($main, $mainPath, $ext);
        $variants = [];
        if (function_exists('imagewebp')) {
            foreach (self::SIZES as $s) {
                if ($s >= $mw && $s !== self::SIZES[0]) continue;
                $v = self::resize($main, $mw, $mh, $s);
                $vp = "$dir/$base-$s.webp";
                imagewebp($v, $vp, 80);
                $variants[$s] = "$rel/$base-$s.webp";
                if ($v !== $main) imagedestroy($v);
            }
            if ($ext !== 'webp') {
                imagewebp($main, "$dir/$base.webp", 82);
                $variants['webp'] = "$rel/$base.webp";
            }
        }
        if ($main !== $src) imagedestroy($main);
        imagedestroy($src);

        $id = db_insert($db, 'media', [
            'path' => "$rel/$base.$ext", 'mime' => self::MIMES[$info[2]][0], 'width' => $mw, 'height' => $mh,
            'size' => (int) filesize($mainPath), 'alt' => mb_substr($alt, 0, 250), 'title' => mb_substr(pathinfo($origName, PATHINFO_FILENAME), 0, 200),
            'variants' => je($variants), 'source_url' => $sourceUrl, 'created_at' => now(),
        ]);
        return ['ok' => true, 'media' => db_one($db, 'SELECT * FROM media WHERE id = ?', [$id])];
    }

    /** http(s) URL whose host resolves only to public addresses (no loopback, private, link-local or reserved ranges). */
    public static function publicHttpUrl(string $url): bool
    {
        if (!preg_match('#^https?://#i', $url)) return false;
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') return false;
        $ips = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) ? [trim($host, '[]')] : (gethostbynamel($host) ?: []);
        if (!$ips) return false;
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        }
        return true;
    }

    /** Download a remote image (imports). Only http(s), size-capped, then validated as an upload. */
    public static function fromUrl(PDO $db, int $shopId, string $url, string $alt = ''): array
    {
        if (!preg_match('#^https?://#i', $url)) return ['ok' => false, 'error' => 'Μη έγκυρο URL εικόνας'];
        $existing = db_one($db, 'SELECT * FROM media WHERE source_url = ?', [$url]);
        if ($existing) return ['ok' => true, 'media' => $existing];
        // Follow at most 3 redirects manually, re-validating every hop so a public URL cannot bounce to an internal address.
        $target = $url;
        $in = null;
        for ($hop = 0; $hop <= 3; $hop++) {
            if (!self::publicHttpUrl($target)) return ['ok' => false, 'error' => 'Μη επιτρεπτός διακομιστής εικόνας'];
            $ctx = stream_context_create(['http' => ['timeout' => 15, 'follow_location' => 0, 'ignore_errors' => true, 'user_agent' => 'wechevo-eshop-importer/1.0']]);
            $in = @fopen($target, 'rb', false, $ctx);
            if (!$in) return ['ok' => false, 'error' => 'Η εικόνα δεν κατέβηκε'];
            $headers = stream_get_meta_data($in)['wrapper_data'] ?? [];
            $status = 0; $location = null;
            foreach ((array) $headers as $hl) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $hl, $m)) { $status = (int) $m[1]; $location = null; }
                elseif (stripos((string) $hl, 'Location:') === 0) $location = trim(substr((string) $hl, 9));
            }
            if ($status >= 300 && $status < 400 && $location) {
                fclose($in); $in = null;
                $target = preg_match('#^https?://#i', $location) ? $location : (parse_url($target, PHP_URL_SCHEME) . '://' . parse_url($target, PHP_URL_HOST) . '/' . ltrim($location, '/'));
                continue;
            }
            if ($status !== 200) { fclose($in); return ['ok' => false, 'error' => 'Η εικόνα δεν κατέβηκε (HTTP ' . $status . ')']; }
            break;
        }
        if (!$in) return ['ok' => false, 'error' => 'Πάρα πολλές ανακατευθύνσεις'];
        $tmp = tempnam(ensure_dir(DATA_DIR . '/tmp'), 'img');
        $out = fopen($tmp, 'wb');
        $bytes = 0;
        while (!feof($in)) {
            $chunk = fread($in, 65536);
            $bytes += strlen((string) $chunk);
            if ($bytes > self::MAX_BYTES) { fclose($in); fclose($out); @unlink($tmp); return ['ok' => false, 'error' => 'Εικόνα > 10 MB']; }
            fwrite($out, (string) $chunk);
        }
        fclose($in);
        fclose($out);
        $r = self::fromPath($db, $shopId, $tmp, basename((string) parse_url($url, PHP_URL_PATH)) ?: 'image', $alt, $url);
        @unlink($tmp);
        return $r;
    }

    private static function resize($img, int $w, int $h, int $max)
    {
        if ($w <= $max && $h <= $max) return $img;
        $r = min($max / $w, $max / $h);
        $nw = max(1, (int) round($w * $r));
        $nh = max(1, (int) round($h * $r));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    private static function save($img, string $path, string $ext): void
    {
        match ($ext) {
            'png' => (function () use ($img, $path) { imagesavealpha($img, true); imagepng($img, $path, 7); })(),
            'webp' => imagewebp($img, $path, 82),
            'gif' => imagegif($img, $path),
            default => imagejpeg($img, $path, 84),
        };
    }

    public static function delete(PDO $db, int $mediaId): void
    {
        $m = db_one($db, 'SELECT * FROM media WHERE id = ?', [$mediaId]);
        if (!$m) return;
        $files = array_merge([$m['path']], array_values(jd($m['variants'])));
        foreach ($files as $f) {
            $p = realpath(UPLOAD_DIR . '/' . $f);
            if ($p && str_starts_with($p, realpath(UPLOAD_DIR) . DIRECTORY_SEPARATOR)) @unlink($p);
        }
        db_exec($db, 'DELETE FROM media WHERE id = ?', [$mediaId]);
    }
}

/** Build <img>/<picture> markup for a media row. */
function media_url(?array $m, ?int $size = null): string
{
    if (!$m) return '';
    $v = jd($m['variants'] ?? '{}');
    if ($size && isset($v[$size])) return '/uploads/' . $v[$size];
    return '/uploads/' . $m['path'];
}
function media_img(?array $m, string $sizes = '100vw', string $class = '', bool $eager = false, string $alt = ''): string
{
    if (!$m) return '';
    $v = jd($m['variants'] ?? '{}');
    $srcset = [];
    foreach (ImageUpload::SIZES as $s) { if (isset($v[$s])) $srcset[] = '/uploads/' . $v[$s] . ' ' . min($s, (int) $m['width']) . 'w'; }
    if (isset($v['webp'])) $srcset[] = '/uploads/' . $v['webp'] . ' ' . (int) $m['width'] . 'w';
    $alt = $alt !== '' ? $alt : (string) ($m['alt'] ?? '');
    $attrs = 'src="' . e('/uploads/' . $m['path']) . '" width="' . (int) $m['width'] . '" height="' . (int) $m['height'] . '" alt="' . e($alt) . '"'
        . ($class ? ' class="' . e($class) . '"' : '') . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async"';
    if ($srcset) $attrs .= ' srcset="' . e(implode(', ', $srcset)) . '" sizes="' . e($sizes) . '"';
    return "<img $attrs>";
}
