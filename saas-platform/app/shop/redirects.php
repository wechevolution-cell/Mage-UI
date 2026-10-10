<?php
declare(strict_types=1);

/** Redirect manager (301/302/307/308/410/451, exact or regex with $1) + 404 monitor. */
final class Redirects
{
    public const CODES = [301 => '301 Μόνιμη', 302 => '302 Προσωρινή', 307 => '307 Προσωρινή (διατήρηση μεθόδου)', 308 => '308 Μόνιμη (διατήρηση μεθόδου)', 410 => '410 Διαγράφηκε οριστικά', 451 => '451 Μη διαθέσιμο για νομικούς λόγους'];
    private const NOISE = '#(wp-login|wp-admin|xmlrpc|\.env|\.git|phpmyadmin|\.php$|/vendor/|cgi-bin|\.aws|config\.json|\.sql|\.bak|eval-stdin|/owa/|autodiscover|actuator|/boaform|\.well-known/(?!security\.txt))#i';

    public static function normalize(string $path): string
    {
        $path = trim($path);
        if (preg_match('#^https?://#i', $path)) $path = (string) (parse_url($path, PHP_URL_PATH) ?? '/') . (($q = parse_url($path, PHP_URL_QUERY)) ? '?' . $q : '');
        return '/' . ltrim($path, '/');
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public static function save(PDO $db, array $in, ?int $id = null): array
    {
        $type = ($in['match_type'] ?? 'exact') === 'regex' ? 'regex' : 'exact';
        $source = $type === 'regex' ? trim((string) $in['source']) : self::normalize((string) $in['source']);
        $code = (int) ($in['code'] ?? 301);
        if (!isset(self::CODES[$code])) return ['ok' => false, 'error' => 'Μη έγκυρος κωδικός ανακατεύθυνσης.'];
        $target = in_array($code, [410, 451], true) ? null : trim((string) ($in['target'] ?? ''));
        if ($source === '' || $source === '/') return ['ok' => false, 'error' => 'Συμπληρώστε τη διαδρομή προέλευσης.'];
        if ($type === 'regex' && @preg_match('#' . str_replace('#', '\#', $source) . '#u', '') === false) return ['ok' => false, 'error' => 'Μη έγκυρο regex.'];
        if ($target !== null) {
            if ($target === '') return ['ok' => false, 'error' => 'Συμπληρώστε τον προορισμό.'];
            if (!preg_match('#^https?://#i', $target)) $target = self::normalize($target);
            if (!Sanitizer::safeUrl($target)) return ['ok' => false, 'error' => 'Μη έγκυρος προορισμός.'];
            if ($type === 'exact' && rtrim($target, '/') === rtrim($source, '/')) return ['ok' => false, 'error' => 'Η προέλευση και ο προορισμός δεν μπορεί να είναι ίδια.'];
            if ($type === 'exact' && self::wouldLoop($db, $source, $target, $id)) return ['ok' => false, 'error' => 'Η ανακατεύθυνση θα δημιουργούσε βρόχο (loop).'];
        }
        $row = ['source' => $source, 'target' => $target, 'code' => $code, 'match_type' => $type, 'active' => !empty($in['active']) || !isset($in['active']) ? 1 : 0, 'note' => mb_substr((string) ($in['note'] ?? ''), 0, 200) ?: null];
        if ($id) { db_update($db, 'redirects', $row, 'id = ?', [$id]); }
        else {
            $existing = db_val($db, 'SELECT id FROM redirects WHERE source = ? AND match_type = ?', [$source, $type]);
            if ($existing) { db_update($db, 'redirects', $row, 'id = ?', [$existing]); $id = (int) $existing; }
            else { $row['created_at'] = now(); $id = db_insert($db, 'redirects', $row); }
        }
        // Flatten chains: anything pointing to this source now points to the final target
        if ($target !== null && $type === 'exact') {
            db_exec($db, "UPDATE redirects SET target = ? WHERE target = ? AND match_type = 'exact' AND id <> ?", [$target, $source, $id]);
        }
        db_exec($db, 'UPDATE not_found_log SET ignored = 1 WHERE path = ?', [$source]);
        return ['ok' => true, 'id' => $id];
    }

    private static function wouldLoop(PDO $db, string $source, string $target, ?int $id): bool
    {
        $seen = [rtrim($source, '/') => true];
        $cur = $target;
        for ($i = 0; $i < 20; $i++) {
            $k = rtrim($cur, '/');
            if (isset($seen[$k])) return true;
            $seen[$k] = true;
            $next = db_val($db, "SELECT target FROM redirects WHERE active = 1 AND match_type = 'exact' AND (source = ? OR source = ?)" . ($id ? ' AND id <> ?' : ''), $id ? [$k, $k . '/', $id] : [$k, $k . '/']);
            if (!$next) return false;
            $cur = $next;
        }
        return true;
    }

    /** Automatic 301 when a slug changes. */
    public static function autoCreate(PDO $db, string $from, string $to): void
    {
        if ($from === $to) return;
        db_exec($db, "DELETE FROM redirects WHERE source = ? AND match_type = 'exact'", [$to]); // the new URL must not redirect away
        self::save($db, ['source' => $from, 'target' => $to, 'code' => 301, 'match_type' => 'exact', 'note' => 'Αυτόματη (αλλαγή slug)']);
    }

    /** @return array{code:int,target:?string}|null */
    public static function match(PDO $db, string $pathWithQuery): ?array
    {
        $path = self::normalize($pathWithQuery);
        $alt = str_ends_with($path, '/') ? rtrim($path, '/') : $path . '/';
        $r = db_one($db, "SELECT * FROM redirects WHERE active = 1 AND match_type = 'exact' AND source IN (?, ?) LIMIT 1", [$path, $alt]);
        if (!$r) {
            foreach (db_all($db, "SELECT * FROM redirects WHERE active = 1 AND match_type = 'regex' ORDER BY id") as $rx) {
                $re = '#' . str_replace('#', '\#', $rx['source']) . '#u';
                if (@preg_match($re, $path, $m)) {
                    $r = $rx;
                    if ($r['target']) $r['target'] = preg_replace_callback('/\$(\d+)/', fn($x) => $m[(int) $x[1]] ?? '', $r['target']);
                    break;
                }
            }
        }
        if (!$r) return null;
        db_exec($db, 'UPDATE redirects SET hits = hits + 1, last_hit_at = ? WHERE id = ?', [now(), $r['id']]);
        return ['code' => (int) $r['code'], 'target' => $r['target']];
    }

    public static function log404(PDO $db, string $path): void
    {
        if (preg_match(self::NOISE, $path) || strlen($path) > 500) return;
        $ref = mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500) ?: null;
        db_exec($db, 'INSERT INTO not_found_log(path, hits, referrer, last_hit_at, created_at) VALUES(?,1,?,?,?)
            ON CONFLICT(path) DO UPDATE SET hits = hits + 1, referrer = COALESCE(excluded.referrer, referrer), last_hit_at = excluded.last_hit_at', [$path, $ref, now(), now()]);
    }

    /** Suggest a destination for a 404 path from product/category slugs. */
    public static function suggest(PDO $db, string $path): ?string
    {
        $last = slugify((string) basename(rtrim($path, '/')));
        if ($last === '' || $last === 'item') return null;
        $p = db_val($db, 'SELECT slug FROM products WHERE deleted_at IS NULL AND is_active = 1 AND slug LIKE ? LIMIT 1', ['%' . substr($last, 0, 40) . '%']);
        if ($p) return '/product/' . $p . '/';
        $c = db_val($db, "SELECT path FROM categories WHERE deleted_at IS NULL AND slug LIKE ? LIMIT 1", ['%' . substr($last, 0, 40) . '%']);
        return $c ? '/category/' . $c . '/' : null;
    }
}
