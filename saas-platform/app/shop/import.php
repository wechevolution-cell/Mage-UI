<?php
declare(strict_types=1);

/**
 * Bulk import: Upload → Validate → Preview → Confirm → Queue → Process (batches) → Report.
 * Rows are staged in import_rows and processed in small batches (AJAX polling and/or cron),
 * so 10,000 rows never hit a PHP timeout. SKU match → update, otherwise create. Activation respects the quota.
 */
final class Importer
{
    public const FIELDS = [
        'sku' => 'SKU', 'name' => 'Όνομα', 'price' => 'Τιμή', 'sale_price' => 'Τιμή προσφοράς', 'cost_price' => 'Κόστος', 'stock' => 'Απόθεμα',
        'barcode' => 'Barcode / EAN', 'mpn' => 'MPN', 'brand' => 'Μάρκα', 'category' => 'Κατηγορία (Γονική > Υπο)', 'short_description' => 'Σύντομη περιγραφή',
        'description' => 'Περιγραφή', 'images' => 'Εικόνες (URL, χωρισμένα με , ή |)', 'weight' => 'Βάρος', 'availability' => 'Διαθεσιμότητα',
        'active' => 'Ενεργό (1/0)', 'slug' => 'Slug', 'seo_title' => 'SEO τίτλος', 'seo_description' => 'SEO περιγραφή', 'tags' => 'Ετικέτες',
        'attributes' => 'Χαρακτηριστικά (Όνομα:Τιμή;Όνομα:Τιμή)',
    ];
    private const ALIASES = [
        'sku' => ['sku', 'κωδικος', 'κωδικός', 'code', 'product_code', 'id', 'unique_id', 'uniqueid'],
        'name' => ['name', 'title', 'ονομα', 'όνομα', 'τιτλος', 'τίτλος', 'product_name', 'product'],
        'price' => ['price', 'τιμη', 'τιμή', 'regular_price', 'price_with_vat', 'retail_price'],
        'sale_price' => ['sale_price', 'special_price', 'τιμη_προσφορας', 'τιμή προσφοράς', 'offer_price'],
        'cost_price' => ['cost', 'cost_price', 'κοστος', 'κόστος', 'wholesale_price'],
        'stock' => ['stock', 'qty', 'quantity', 'αποθεμα', 'απόθεμα', 'stock_quantity'],
        'barcode' => ['barcode', 'ean', 'gtin', 'upc'],
        'mpn' => ['mpn', 'part_number'],
        'brand' => ['brand', 'manufacturer', 'μαρκα', 'μάρκα', 'κατασκευαστης', 'κατασκευαστής'],
        'category' => ['category', 'categories', 'κατηγορια', 'κατηγορία', 'category_path'],
        'short_description' => ['short_description', 'summary', 'συντομη_περιγραφη'],
        'description' => ['description', 'περιγραφη', 'περιγραφή', 'long_description', 'body'],
        'images' => ['images', 'image', 'image_url', 'image_link', 'εικονα', 'εικόνα', 'additional_image_link', 'additionalimage'],
        'weight' => ['weight', 'βαρος', 'βάρος'],
        'availability' => ['availability', 'διαθεσιμοτητα', 'διαθεσιμότητα', 'instock'],
        'active' => ['active', 'status', 'enabled', 'is_active', 'ενεργο', 'ενεργό'],
        'slug' => ['slug', 'url_key'],
        'seo_title' => ['seo_title', 'meta_title'],
        'seo_description' => ['seo_description', 'meta_description'],
        'tags' => ['tags', 'ετικετες'],
        'attributes' => ['attributes', 'χαρακτηριστικα', 'specs'],
    ];
    public const BATCH = 200;

    /** Stage the uploaded file: parse headers + rows into import_rows. */
    public static function stage(PDO $db, array $shop, array $file, int $userId): array
    {
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'Δεν ανέβηκε αρχείο.'];
        if ($file['size'] > 50 * 1024 * 1024) return ['ok' => false, 'error' => 'Μέγιστο μέγεθος αρχείου 50 MB.'];
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt', 'xml'], true)) return ['ok' => false, 'error' => 'Επιτρέπονται αρχεία CSV ή XML.'];
        $dir = ensure_dir(DATA_DIR . '/imports/shop_' . (int) $shop['id']);
        $dest = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . ($ext === 'xml' ? 'xml' : 'csv');
        if (!(is_uploaded_file($file['tmp_name']) ? move_uploaded_file($file['tmp_name'], $dest) : copy($file['tmp_name'], $dest))) return ['ok' => false, 'error' => 'Αποτυχία αποθήκευσης αρχείου.'];
        return self::stageFile($db, $shop, $dest, (string) $file['name'], $ext === 'xml' ? 'xml' : 'csv', $userId);
    }

    public static function stageFile(PDO $db, array $shop, string $path, string $name, string $type, int $userId): array
    {
        $importId = db_insert($db, 'imports', ['type' => $type, 'filename' => mb_substr($name, 0, 200), 'path' => $path, 'status' => 'uploaded', 'user_id' => $userId, 'created_at' => now()]);
        try {
            $count = 0;
            $headers = [];
            $db->exec('BEGIN IMMEDIATE');
            $ins = $db->prepare('INSERT INTO import_rows(import_id, row_no, data, status) VALUES(?,?,?,\'pending\')');
            $max = Shops::storedLimit($shop) + 5000;
            foreach (($type === 'xml' ? self::readXml($path) : self::readCsv($path)) as $row) {
                if ($count === 0) $headers = array_keys($row);
                else $headers = array_values(array_unique(array_merge($headers, array_keys($row))));
                $count++;
                if ($count > $max) throw new RuntimeException('Το αρχείο υπερβαίνει τις ' . $max . ' γραμμές.');
                $ins->execute([$importId, $count, je($row)]);
            }
            $db->exec('COMMIT');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->exec('ROLLBACK');
            db_update($db, 'imports', ['status' => 'failed'], 'id = ?', [$importId]);
            self::log($db, $importId, 'error', 'Αποτυχία ανάγνωσης: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Το αρχείο δεν διαβάστηκε: ' . $e->getMessage(), 'import_id' => $importId];
        }
        if ($count === 0) {
            db_update($db, 'imports', ['status' => 'failed'], 'id = ?', [$importId]);
            return ['ok' => false, 'error' => 'Το αρχείο δεν περιέχει γραμμές προϊόντων.', 'import_id' => $importId];
        }
        $mapping = self::guessMapping($headers);
        db_update($db, 'imports', ['status' => 'validated', 'total_rows' => $count, 'headers' => je($headers), 'mapping' => je($mapping)], 'id = ?', [$importId]);
        self::log($db, $importId, 'info', "Αναγνώστηκαν $count γραμμές.");
        return ['ok' => true, 'import_id' => $importId, 'rows' => $count];
    }

    /** CSV reader: auto-detect delimiter (, ; tab |), strip BOM, Greek Windows-1253 fallback. */
    public static function readCsv(string $path): Generator
    {
        $fh = fopen($path, 'rb');
        $first = (string) fgets($fh);
        rewind($fh);
        $bom = str_starts_with($first, "\xEF\xBB\xBF");
        $delims = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t"), '|' => substr_count($first, '|')];
        arsort($delims);
        $delim = (string) array_key_first($delims);
        $utf8 = mb_check_encoding($first, 'UTF-8');
        if ($bom) fread($fh, 3);
        $headers = null;
        while (($cols = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
            if ($cols === [null] || (count($cols) === 1 && trim((string) $cols[0]) === '')) continue;
            if (!$utf8) $cols = array_map(fn($c) => mb_convert_encoding((string) $c, 'UTF-8', 'Windows-1253'), $cols);
            if ($headers === null) {
                $headers = array_map(fn($h) => trim((string) $h), $cols);
                continue;
            }
            $row = [];
            foreach ($headers as $i => $h) { if ($h !== '') $row[$h] = isset($cols[$i]) ? trim((string) $cols[$i]) : ''; }
            yield $row;
        }
        fclose($fh);
    }

    /** XML reader (streaming): repeated <product>/<item>/<offer>/<entry>; child elements become columns. */
    public static function readXml(string $path): Generator
    {
        $r = new XMLReader();
        if (!$r->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) throw new RuntimeException('Μη έγκυρο XML');
        $names = ['product', 'item', 'offer', 'entry', 'προϊόν'];
        while (@$r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT && in_array(strtolower($r->localName), $names, true)) {
                $xml = $r->readOuterXml();
                $node = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
                if (!$node) continue;
                $row = [];
                foreach ($node->attributes() as $k => $v) $row[(string) $k] = trim((string) $v);
                foreach ($node->children() as $child) {
                    $k = $child->getName();
                    if ($child->count() > 0) {
                        $vals = [];
                        foreach ($child->children() as $cc) $vals[] = trim((string) $cc);
                        $v = implode('|', array_filter($vals));
                    } else {
                        $v = trim((string) $child);
                    }
                    $row[$k] = isset($row[$k]) && $row[$k] !== '' ? $row[$k] . '|' . $v : $v;
                }
                foreach ($node->children('g', true) as $child) { $row[$child->getName()] = trim((string) $child); }
                if ($row) yield $row;
                $r->next();
            }
        }
        $r->close();
    }

    public static function guessMapping(array $headers): array
    {
        $map = [];
        foreach ($headers as $h) {
            $k = str_fold(str_replace([' ', '-'], '_', trim($h)));
            foreach (self::ALIASES as $field => $aliases) {
                if (isset($map[$field])) continue;
                foreach ($aliases as $a) {
                    if ($k === str_fold(str_replace(' ', '_', $a))) { $map[$field] = $h; break 2; }
                }
            }
        }
        return $map;
    }

    /** Validation preview on the first N rows with current mapping. */
    public static function preview(PDO $db, int $importId, int $n = 10): array
    {
        $imp = db_one($db, 'SELECT * FROM imports WHERE id = ?', [$importId]);
        $map = jd($imp['mapping']);
        $rows = db_all($db, 'SELECT row_no, data FROM import_rows WHERE import_id = ? ORDER BY row_no LIMIT ?', [$importId, $n]);
        $out = [];
        foreach ($rows as $r) {
            $mapped = self::mapRow(jd($r['data']), $map);
            $out[] = ['row' => $r['row_no'], 'data' => $mapped, 'errors' => self::validate($mapped)];
        }
        $skus = (int) db_val($db, 'SELECT COUNT(*) FROM import_rows WHERE import_id = ?', [$importId]);
        return ['rows' => $out, 'total' => $skus];
    }

    public static function mapRow(array $row, array $map): array
    {
        $out = [];
        foreach (self::FIELDS as $f => $_) { if (!empty($map[$f]) && array_key_exists($map[$f], $row)) $out[$f] = $row[$map[$f]]; }
        return $out;
    }

    public static function validate(array $m): array
    {
        $e = [];
        if (trim((string) ($m['name'] ?? '')) === '' && trim((string) ($m['sku'] ?? '')) === '') $e[] = 'Λείπει όνομα και SKU';
        if (isset($m['price']) && $m['price'] !== '' && self::price($m['price']) === null) $e[] = 'Μη έγκυρη τιμή';
        return $e;
    }

    public static function price(mixed $v): ?float
    {
        $v = trim(str_replace(['€', ' ', "\xC2\xA0"], '', (string) $v));
        if ($v === '') return null;
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $v)) $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
        return is_numeric($v) ? round((float) $v, 2) : null;
    }

    public static function confirm(PDO $db, int $importId, array $mapping, array $options): array
    {
        $imp = db_one($db, 'SELECT * FROM imports WHERE id = ?', [$importId]);
        if (!$imp || !in_array($imp['status'], ['validated', 'uploaded'], true)) return ['ok' => false, 'error' => 'Η εισαγωγή δεν μπορεί να επιβεβαιωθεί.'];
        $mapping = array_intersect_key(array_filter($mapping, 'strlen'), self::FIELDS);
        if (empty($mapping['name']) && empty($mapping['sku'])) return ['ok' => false, 'error' => 'Αντιστοιχίστε τουλάχιστον το Όνομα ή το SKU.'];
        $opts = ['update_existing' => !empty($options['update_existing']), 'activate' => in_array($options['activate'] ?? 'file', ['file', 'all', 'none'], true) ? $options['activate'] : 'file',
            'download_images' => !empty($options['download_images'])];
        db_update($db, 'imports', ['mapping' => je($mapping), 'options' => je($opts), 'status' => 'queued'], 'id = ?', [$importId]);
        self::log($db, $importId, 'info', 'Η εισαγωγή μπήκε σε ουρά.');
        return ['ok' => true];
    }

    /** Process one batch. Safe to call repeatedly (AJAX polling / cron). */
    public static function processBatch(PDO $db, array $shop, int $importId, int $batch = self::BATCH, float $timeBudget = 8.0): array
    {
        $t0 = microtime(true);
        $imp = db_one($db, 'SELECT * FROM imports WHERE id = ?', [$importId]);
        if (!$imp || !in_array($imp['status'], ['queued', 'processing'], true)) return self::progress($db, $importId);
        if ($imp['status'] === 'queued') db_update($db, 'imports', ['status' => 'processing', 'started_at' => now()], 'id = ?', [$importId]);
        $map = jd($imp['mapping']);
        $opts = jd($imp['options']);
        $rows = db_all($db, "SELECT * FROM import_rows WHERE import_id = ? AND status = 'pending' ORDER BY row_no LIMIT ?", [$importId, $batch]);
        $c = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'activated' => 0, 'quota' => 0];
        $imageQueue = [];
        $db->exec('BEGIN IMMEDIATE');
        foreach ($rows as $r) {
            if (microtime(true) - $t0 > $timeBudget) break;
            $m = self::mapRow(jd($r['data']), $map);
            try {
                $res = self::upsert($db, $shop, $m, $opts);
                $c[$res['action']]++;
                if (!empty($res['activated'])) $c['activated']++;
                if (!empty($res['quota_blocked'])) { $c['quota']++; }
                if (!empty($res['images'])) $imageQueue[$res['id']] = $res['images'];
                db_exec($db, 'UPDATE import_rows SET status = ?, message = ? WHERE id = ?', [$res['action'] === 'skipped' ? 'skipped' : 'done', $res['message'] ?? null, $r['id']]);
                if (!empty($res['message']) && $res['action'] === 'skipped') self::log($db, $importId, 'warning', $res['message'], (int) $r['row_no']);
            } catch (Throwable $e) {
                $c['errors']++;
                db_exec($db, "UPDATE import_rows SET status = 'error', message = ? WHERE id = ?", [mb_substr($e->getMessage(), 0, 300), $r['id']]);
                self::log($db, $importId, 'error', $e->getMessage(), (int) $r['row_no']);
            }
        }
        $db->exec('COMMIT');
        if (!empty($opts['download_images'])) {
            foreach ($imageQueue as $pid => $urls) {
                if (microtime(true) - $t0 > $timeBudget + 10) break;
                self::attachImages($db, $shop, (int) $pid, $urls);
            }
        }
        db_exec($db, 'UPDATE imports SET processed_rows = (SELECT COUNT(*) FROM import_rows WHERE import_id = ? AND status <> \'pending\'),
            created_count = created_count + ?, updated_count = updated_count + ?, skipped_count = skipped_count + ?, error_count = error_count + ?,
            activated_count = activated_count + ?, quota_blocked_count = quota_blocked_count + ? WHERE id = ?',
            [$importId, $c['created'], $c['updated'], $c['skipped'], $c['errors'], $c['activated'], $c['quota'], $importId]);
        $left = (int) db_val($db, "SELECT COUNT(*) FROM import_rows WHERE import_id = ? AND status = 'pending'", [$importId]);
        if ($left === 0) {
            db_update($db, 'imports', ['status' => 'completed', 'finished_at' => now()], 'id = ?', [$importId]);
            $p = self::progress($db, $importId);
            self::log($db, $importId, 'info', "Ολοκληρώθηκε: {$p['created']} νέα, {$p['updated']} ενημερώσεις, {$p['skipped']} παραλείψεις, {$p['errors']} σφάλματα, {$p['activated']} ενεργοποιήσεις.");
            if ($p['quota_blocked'] > 0) self::log($db, $importId, 'warning', "{$p['quota_blocked']} προϊόντα αποθηκεύτηκαν ως ανενεργά λόγω ορίου πακέτου (" . Quota::limit($shop) . ' ενεργά).');
            try {
                Notifications::create('import_done', (int) $shop['id'], 'Ολοκλήρωση εισαγωγής: ' . $shop['name'], "{$p['created']} νέα / {$p['updated']} ενημερώσεις / {$p['errors']} σφάλματα", 'import:' . $shop['id'] . ':' . $importId, false);
            } catch (Throwable) {
            }
        }
        return self::progress($db, $importId);
    }

    /** @return array{action:string,id?:int,activated?:bool,quota_blocked?:bool,images?:array,message?:string} */
    public static function upsert(PDO $db, array $shop, array $m, array $opts): array
    {
        $sku = trim((string) ($m['sku'] ?? ''));
        $name = trim(strip_tags((string) ($m['name'] ?? '')));
        if ($name === '' && $sku === '') return ['action' => 'skipped', 'message' => 'Χωρίς όνομα και SKU'];
        $existing = $sku !== '' ? db_one($db, 'SELECT * FROM products WHERE sku = ? AND deleted_at IS NULL', [$sku]) : null;
        if ($existing && empty($opts['update_existing'])) return ['action' => 'skipped', 'message' => "Το SKU $sku υπάρχει ήδη (ενημέρωση απενεργοποιημένη)"];
        if (!$existing && $name === '') return ['action' => 'skipped', 'message' => "Νέο SKU $sku χωρίς όνομα"];
        if (!$existing && Quota::storedLimitReached($db, $shop)) return ['action' => 'skipped', 'message' => 'Όριο αποθηκευμένων προϊόντων'];
        $price = self::price($m['price'] ?? '');
        if (!$existing && $price === null) $price = 0.0;

        $row = ['updated_at' => now()];
        if ($name !== '') $row['name'] = mb_substr($name, 0, 250);
        if ($price !== null) $row['price'] = $price;
        if (array_key_exists('sale_price', $m)) { $sp = self::price($m['sale_price']); $row['sale_price'] = ($sp && $sp < ($price ?? (float) ($existing['price'] ?? 0))) ? $sp : null; }
        if (array_key_exists('cost_price', $m)) $row['cost_price'] = self::price($m['cost_price']);
        if (array_key_exists('stock', $m) && $m['stock'] !== '') $row['stock'] = (int) preg_replace('/[^\d\-]/', '', (string) $m['stock']);
        foreach (['barcode', 'mpn', 'brand', 'tags'] as $f) { if (array_key_exists($f, $m)) $row[$f] = trim((string) $m[$f]) ?: null; }
        if (array_key_exists('short_description', $m)) $row['short_description'] = Sanitizer::html((string) $m['short_description']);
        if (array_key_exists('description', $m)) $row['description'] = Sanitizer::html((string) $m['description']);
        if (array_key_exists('weight', $m) && $m['weight'] !== '') $row['weight'] = self::price($m['weight']);
        if (array_key_exists('seo_title', $m)) $row['seo_title'] = Sanitizer::text($m['seo_title'], 200) ?: null;
        if (array_key_exists('seo_description', $m)) $row['seo_description'] = Sanitizer::text($m['seo_description'], 320) ?: null;
        if (array_key_exists('availability', $m) && $m['availability'] !== '') $row['availability'] = self::availability((string) $m['availability']);
        $catId = !empty($m['category']) ? Categories::ensurePath($db, (string) $m['category']) : null;
        if ($catId) $row['primary_category_id'] = $catId;

        if ($existing) {
            db_update($db, 'products', $row, 'id = ?', [$existing['id']]);
            $id = (int) $existing['id'];
            $action = 'updated';
        } else {
            $row['slug'] = Products::uniqueSlug($db, slugify(!empty($m['slug']) ? $m['slug'] : $name, 90));
            $row['sku'] = $sku ?: null;
            $row['manage_stock'] = array_key_exists('stock', $m) && $m['stock'] !== '' ? 1 : 0;
            $row['is_active'] = 0;
            $row['created_at'] = now();
            $row += ['price' => 0];
            $id = db_insert($db, 'products', $row);
            $action = 'created';
        }
        if ($catId) db_exec($db, 'INSERT OR IGNORE INTO product_categories(product_id, category_id) VALUES(?,?)', [$id, $catId]);
        if (!empty($m['attributes'])) {
            db_exec($db, 'DELETE FROM product_attributes WHERE product_id = ?', [$id]);
            foreach (array_filter(array_map('trim', preg_split('/[;\n]/u', (string) $m['attributes']))) as $i => $pair) {
                [$an, $av] = array_pad(array_map('trim', explode(':', $pair, 2)), 2, '');
                if ($an !== '' && $av !== '') db_insert($db, 'product_attributes', ['product_id' => $id, 'name' => mb_substr($an, 0, 100), 'value' => mb_substr($av, 0, 250), 'filterable' => 1, 'sort' => $i]);
            }
        }
        // Activation through the quota engine
        $want = match ($opts['activate'] ?? 'file') {
            'all' => true,
            'none' => false,
            default => array_key_exists('active', $m) ? self::truthy((string) $m['active']) : true,
        };
        $activated = false;
        $blocked = false;
        if ($want) {
            $res = Quota::setActive($db, $shop, [$id], true);
            if ($res['ok'] && $res['changed'] > 0) $activated = true;
            if (!$res['ok']) $blocked = true;
        } elseif (array_key_exists('active', $m) && ($opts['activate'] ?? 'file') !== 'none') {
            Quota::setActive($db, $shop, [$id], false);
        }
        $images = !empty($m['images']) ? array_values(array_filter(array_map('trim', preg_split('/[,|\s]+(?=https?:)/', (string) $m['images'])))) : [];
        return ['action' => $action, 'id' => $id, 'activated' => $activated, 'quota_blocked' => $blocked, 'images' => $images];
    }

    public static function attachImages(PDO $db, array $shop, int $productId, array $urls): void
    {
        $have = (int) db_val($db, 'SELECT COUNT(*) FROM product_images WHERE product_id = ?', [$productId]);
        foreach (array_slice($urls, 0, 8) as $i => $url) {
            $r = ImageUpload::fromUrl($db, (int) $shop['id'], $url, (string) db_val($db, 'SELECT name FROM products WHERE id = ?', [$productId]));
            if (!$r['ok']) continue;
            $mid = (int) $r['media']['id'];
            if (db_val($db, 'SELECT 1 FROM product_images WHERE product_id = ? AND media_id = ?', [$productId, $mid])) continue;
            db_insert($db, 'product_images', ['product_id' => $productId, 'media_id' => $mid, 'sort' => $have + $i]);
            if ($have + $i === 0) db_exec($db, 'UPDATE products SET image_id = ? WHERE id = ?', [$mid, $productId]);
        }
    }

    public static function availability(string $v): string
    {
        $f = str_fold($v);
        if (in_array($f, ['0', 'no', 'false', 'out of stock', 'out_of_stock', 'εξαντλημενο', 'μη διαθεσιμο'], true)) return 'out_of_stock';
        if (str_contains($f, 'preorder') || str_contains($f, 'προπαραγγελια')) return 'preorder';
        if (str_contains($f, 'backorder') || str_contains($f, 'κατοπιν') || str_contains($f, 'ημερες')) return 'backorder';
        return 'in_stock';
    }

    public static function truthy(string $v): bool
    {
        return in_array(str_fold(trim($v)), ['1', 'yes', 'true', 'y', 'ναι', 'active', 'ενεργο', 'enabled', 'publish', 'published'], true);
    }

    public static function progress(PDO $db, int $importId): array
    {
        $i = db_one($db, 'SELECT * FROM imports WHERE id = ?', [$importId]);
        if (!$i) return ['status' => 'missing'];
        return ['id' => (int) $i['id'], 'status' => $i['status'], 'total' => (int) $i['total_rows'], 'processed' => (int) $i['processed_rows'],
            'created' => (int) $i['created_count'], 'updated' => (int) $i['updated_count'], 'skipped' => (int) $i['skipped_count'], 'errors' => (int) $i['error_count'],
            'activated' => (int) $i['activated_count'], 'quota_blocked' => (int) $i['quota_blocked_count'],
            'pct' => $i['total_rows'] ? (int) floor($i['processed_rows'] / $i['total_rows'] * 100) : 0];
    }

    public static function log(PDO $db, int $importId, string $level, string $msg, ?int $row = null): void
    {
        db_insert($db, 'import_logs', ['import_id' => $importId, 'level' => $level, 'row_no' => $row, 'message' => mb_substr($msg, 0, 500), 'created_at' => now()]);
    }
}

/** CSV export: all / active / inactive / by category / selected. Streams rows (memory-safe). */
final class Exporter
{
    public const COLUMNS = ['sku', 'name', 'price', 'sale_price', 'cost_price', 'stock', 'barcode', 'mpn', 'brand', 'category', 'availability', 'active', 'slug', 'short_description', 'description', 'images', 'attributes', 'seo_title', 'seo_description', 'tags', 'url'];

    public static function stream(PDO $db, string $mode, array $ids = [], ?int $categoryId = null): void
    {
        $where = 'p.deleted_at IS NULL';
        $params = [];
        if ($mode === 'active') $where .= ' AND p.is_active = 1';
        elseif ($mode === 'inactive') $where .= ' AND p.is_active = 0';
        elseif ($mode === 'category' && $categoryId) {
            $cids = Categories::descendantIds($db, $categoryId);
            $where .= ' AND p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . in_placeholders($cids) . '))';
            $params = $cids;
        } elseif ($mode === 'selected') {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!$ids) $ids = [0];
            $where .= ' AND p.id IN (' . in_placeholders($ids) . ')';
            $params = $ids;
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products-' . $mode . '-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::COLUMNS, ',', '"', '\\');
        $catPaths = [];
        foreach (Categories::all($db) as $c) $catPaths[$c['id']] = $c;
        $st = db_exec($db, "SELECT p.* FROM products p WHERE $where ORDER BY p.id", $params);
        $base = base_url();
        while ($p = $st->fetch()) {
            $cat = $p['primary_category_id'] && isset($catPaths[$p['primary_category_id']]) ? self::catChain($catPaths, (int) $p['primary_category_id']) : '';
            $imgs = array_map(fn($m) => $base . '/uploads/' . $m['path'], Products::images($db, (int) $p['id']));
            $attrs = implode(';', array_map(fn($a) => $a['name'] . ':' . $a['value'], Products::attributes($db, (int) $p['id'])));
            fputcsv($out, [$p['sku'], $p['name'], $p['price'], $p['sale_price'], $p['cost_price'], $p['stock'], $p['barcode'], $p['mpn'], $p['brand'], $cat, $p['availability'],
                $p['is_active'], $p['slug'], $p['short_description'], $p['description'], implode('|', $imgs), $attrs, $p['seo_title'], $p['seo_description'], $p['tags'],
                $base . '/product/' . $p['slug'] . '/'], ',', '"', '\\');
        }
        fclose($out);
        exit;
    }

    public static function catChain(array $all, int $id): string
    {
        $names = [];
        $guard = 0;
        while ($id && isset($all[$id]) && $guard++ < 30) { array_unshift($names, $all[$id]['name']); $id = (int) $all[$id]['parent_id']; }
        return implode(' > ', $names);
    }
}
