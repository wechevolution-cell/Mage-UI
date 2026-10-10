<?php
declare(strict_types=1);

/** Seeds a new tenant: settings, legal/info pages, menus, feeds, popup draft and optional demo catalog. */
final class ShopSeeder
{
    public static function seed(PDO $db, int $shopId, array $info): void
    {
        $store = ShopSettings::defaults()['store'];
        $store['name'] = $info['shop_name'];
        $store['company_name'] = $info['company_name'] ?? '';
        $store['email'] = $info['email'] ?? '';
        $store['phone'] = $info['phone'] ?? '';
        $store['address'] = $info['address'] ?? '';
        ShopSettings::set($db, 'store', $store);
        $seo = ShopSettings::defaults()['seo'];
        $seo['site_title'] = $info['shop_name'];
        $seo['default_description'] = 'Ηλεκτρονικό κατάστημα ' . $info['shop_name'] . '. Κείμενο δείγμα — επαληθεύστε πριν τη δημοσίευση.';
        ShopSettings::set($db, 'seo', $seo);
        $notif = ShopSettings::defaults()['notifications'];
        $notif['order_email'] = $info['email'] ?? '';
        ShopSettings::set($db, 'notifications', $notif);
        $chk = ShopSettings::defaults()['checkout'];
        $chk['order_prefix'] = strtoupper(substr(preg_replace('/[^a-z]/', '', slugify($info['shop_name'])), 0, 3)) ?: 'ORD';
        ShopSettings::set($db, 'checkout', $chk);

        $T = 'Κείμενο δείγμα — επαληθεύστε πριν τη δημοσίευση.';
        $pages = [
            ['Σχετικά με εμάς', 'about', 'page', '<p>Γράψτε εδώ την ιστορία της επιχείρησής σας: ποιοι είστε, τι πουλάτε και γιατί να σας εμπιστευτεί ο πελάτης. ' . $T . '</p>', 0],
            ['Επικοινωνία', 'contact', 'contact', '<p>Επικοινωνήστε μαζί μας μέσω της φόρμας ή με τα στοιχεία επικοινωνίας του καταστήματος.</p>', 0],
            ['Συχνές ερωτήσεις', 'faq', 'faq', '<p>Βρείτε απαντήσεις στις πιο συχνές ερωτήσεις.</p>', 1],
            ['Αποστολές', 'shipping', 'legal', '<h2>Τρόποι αποστολής</h2><p>Περιγράψτε τους τρόπους και τα κόστη αποστολής. ' . $T . '</p>', 1],
            ['Επιστροφές', 'returns', 'legal', '<h2>Πολιτική επιστροφών</h2><p>Περιγράψτε την πολιτική επιστροφών σύμφωνα με τη νομοθεσία (δικαίωμα υπαναχώρησης 14 ημερών). ' . $T . '</p>', 1],
            ['Όροι χρήσης', 'terms', 'legal', '<h2>Όροι χρήσης</h2><p>Οι όροι χρήσης του καταστήματος. ' . $T . '</p>', 1],
            ['Πολιτική απορρήτου', 'privacy', 'legal', '<h2>Προστασία προσωπικών δεδομένων (GDPR)</h2><p>Περιγράψτε ποια δεδομένα συλλέγετε, για ποιο σκοπό και πώς μπορεί ο χρήστης να ασκήσει τα δικαιώματά του. ' . $T . '</p>', 1],
            ['Πολιτική cookies', 'cookies', 'legal', '<h2>Cookies</h2><p>Χρησιμοποιούμε απαραίτητα cookies και, μόνο με τη συγκατάθεσή σας, cookies στατιστικών (Google Analytics 4). ' . $T . '</p>', 1],
        ];
        $pageIds = [];
        foreach ($pages as $i => [$title, $slug, $kind, $content, $footer]) {
            $faq = $slug === 'faq' ? je([
                ['q' => 'Πόσο κοστίζει η αποστολή;', 'a' => '<p>Το κόστος εξαρτάται από την περιοχή. Δείτε τη σελίδα Αποστολές. ' . $T . '</p>'],
                ['q' => 'Ποιους τρόπους πληρωμής δέχεστε;', 'a' => '<p>Αντικαταβολή και τραπεζική κατάθεση.</p>'],
                ['q' => 'Μπορώ να επιστρέψω ένα προϊόν;', 'a' => '<p>Ναι, εντός 14 ημερών σύμφωνα με την πολιτική επιστροφών. ' . $T . '</p>'],
            ]) : '[]';
            $pageIds[$slug] = db_insert($db, 'pages', ['title' => $title, 'slug' => $slug, 'kind' => $kind, 'content' => $content, 'status' => 'published', 'faq' => $faq,
                'show_in_footer' => $footer, 'sort' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }
        Menus::ensure($db);
        Feeds::ensureDefaults($db);
        db_insert($db, 'popups', ['name' => 'Newsletter αρχικής', 'title' => 'Μάθετε πρώτοι τα νέα!', 'body' => 'Εγγραφείτε για νέα προϊόντα και προσφορές.', 'button_text' => 'Εγγραφή',
            'collect_email' => 1, 'require_consent' => 1, 'consent_text' => 'Συμφωνώ με την Πολιτική απορρήτου και την αποστολή ενημερώσεων.', 'trigger_type' => 'delay', 'delay_seconds' => 8,
            'frequency_days' => 7, 'pages' => 'home', 'status' => 'inactive', 'created_at' => now(), 'updated_at' => now()]);

        $catIds = [];
        if (!empty($info['demo'])) {
            $shop = Shops::find($shopId);
            $catIds = DemoData::install($db, $shop);
        }
        $header = [['label' => 'Αρχική', 'type' => 'home'], ['label' => 'Προϊόντα', 'type' => 'products', 'children' => array_map(fn($c) => ['label' => $c['name'], 'type' => 'category', 'ref_id' => $c['id']], $catIds)]];
        foreach (array_slice($catIds, 0, 4) as $c) $header[] = ['label' => $c['name'], 'type' => 'category', 'ref_id' => $c['id']];
        $header[] = ['label' => 'Σχετικά', 'type' => 'page', 'ref_id' => $pageIds['about']];
        $header[] = ['label' => 'Επικοινωνία', 'type' => 'page', 'ref_id' => $pageIds['contact']];
        Menus::saveTree($db, 'header', $header);
        Menus::saveTree($db, 'footer', [['label' => 'Όροι χρήσης', 'type' => 'page', 'ref_id' => $pageIds['terms']], ['label' => 'Πολιτική απορρήτου', 'type' => 'page', 'ref_id' => $pageIds['privacy']], ['label' => 'Cookies', 'type' => 'page', 'ref_id' => $pageIds['cookies']]]);
        Menus::saveTree($db, 'footer2', [['label' => 'Αποστολές', 'type' => 'page', 'ref_id' => $pageIds['shipping']], ['label' => 'Επιστροφές', 'type' => 'page', 'ref_id' => $pageIds['returns']], ['label' => 'Συχνές ερωτήσεις', 'type' => 'page', 'ref_id' => $pageIds['faq']], ['label' => 'Επικοινωνία', 'type' => 'page', 'ref_id' => $pageIds['contact']]]);
    }
}

/**
 * Optional demo catalog so a new shop isn't empty. Everything is flagged as sample data
 * ("Προϊόν δείγμα") and should be replaced before going live. Images are generated locally (GD).
 */
final class DemoData
{
    public static function sets(): array
    {
        return [
            'fashion' => [['Γυναικεία', ['Παλτό από μαλλί', 'Λινό φόρεμα', 'Πλεκτό πουλόβερ']], ['Ανδρικά', ['Πουκάμισο ποπλίνα', 'Chino παντελόνι']], ['Αξεσουάρ', ['Δερμάτινη τσάντα ώμου', 'Μεταξωτό φουλάρι', 'Δερμάτινη ζώνη']]],
            'electronics' => [['Ήχος', ['Ασύρματα ακουστικά over-ear', 'True wireless ακουστικά', 'Φορητό ηχείο Bluetooth']], ['Κινητά', ['Smartphone 6,5" 128GB', 'Θήκη σιλικόνης']], ['Υπολογιστές', ['Laptop 14" 16GB RAM', 'Ασύρματο ποντίκι', 'Μηχανικό πληκτρολόγιο']]],
            'food' => [['Μέλι & γλυκά', ['Θυμαρίσιο μέλι', 'Μαρμελάδα σύκο', 'Γλυκό κουταλιού βύσσινο']], ['Κελάρι', ['Ελαιόλαδο σε γυάλινη φιάλη', 'Τσάι του βουνού']], ['Δώρα', ['Κουτί δώρου «Καλημέρα»', 'Σαπούνι ελαιολάδου', 'Κεραμική κούπα']]],
            'beauty' => [['Περιποίηση προσώπου', ['Ενυδατική κρέμα ημέρας', 'Serum βιταμίνης C', 'Καθαριστικό gel']], ['Σώμα', ['Λάδι σώματος', 'Scrub σώματος']], ['Μακιγιάζ', ['Κραγιόν ματ', 'Μάσκαρα όγκου', 'Ενισχυτικό λάμψης']]],
            'furniture' => [['Καθιστικό', ['Καναπές τριθέσιος', 'Τραπεζάκι σαλονιού', 'Πολυθρόνα']], ['Φωτισμός', ['Επιδαπέδιο φωτιστικό', 'Κρεμαστό φωτιστικό']], ['Διακόσμηση', ['Κεραμικό βάζο', 'Χαλί από μαλλί', 'Καθρέφτης τοίχου']]],
            'jewelry' => [['Δαχτυλίδια', ['Ασημένιο δαχτυλίδι', 'Χρυσό δαχτυλίδι με πέτρα']], ['Κολιέ', ['Κολιέ με μενταγιόν', 'Αλυσίδα λεπτή']], ['Ρολόγια', ['Ρολόι χειρός κλασικό', 'Ρολόι με μεταλλικό μπρασελέ', 'Σκουλαρίκια κρίκοι']]],
            'sports' => [['Τρέξιμο', ['Αθλητικά παπούτσια τρεξίματος', 'Τεχνικό t-shirt', 'Κολάν τρεξίματος']], ['Γυμναστήριο', ['Αλτήρες ρυθμιζόμενοι', 'Στρώμα yoga']], ['Outdoor', ['Σακίδιο πεζοπορίας 30L', 'Παγούρι θερμός', 'Αντιανεμικό μπουφάν']]],
            'kids' => [['Παιχνίδια', ['Ξύλινα τουβλάκια', 'Λούτρινο αρκουδάκι', 'Παζλ 100 κομματιών']], ['Βρεφικά', ['Βρεφικό κορμάκι', 'Κουβέρτα αγκαλιάς']], ['Σχολικά', ['Σχολική τσάντα', 'Κασετίνα', 'Μπουκάλι νερού']]],
            'hardware' => [['Ηλεκτρικά εργαλεία', ['Δραπανοκατσάβιδο 18V', 'Γωνιακός τροχός', 'Σέγα']], ['Εργαλεία χειρός', ['Σετ κατσαβίδια', 'Γαλλικό κλειδί']], ['Ανταλλακτικά αυτοκινήτου', ['Τακάκια φρένων', 'Φίλτρο λαδιού', 'Μπαταρία αυτοκινήτου']]],
            'pharmacy' => [['Συμπληρώματα', ['Βιταμίνη C 1000mg', 'Βιταμίνη D3', 'Μαγνήσιο']], ['Δερμοκαλλυντικά', ['Αντηλιακή κρέμα SPF50', 'Ενυδατική κρέμα']], ['Μητέρα & παιδί', ['Βρεφικό σαμπουάν', 'Κρέμα αλλαγής πάνας', 'Θερμόμετρο ψηφιακό']]],
        ];
    }

    public static function install(PDO $db, array $shop): array
    {
        $industry = Templates::manifest((string) $shop['template_key'])['demo'] ?? 'fashion';
        $set = self::sets()[$industry] ?? self::sets()['fashion'];
        $palette = (array) (Templates::manifest((string) $shop['template_key'])['demo_palette'] ?? ['#D6CFC3', '#C7CBC4', '#D5CBC6', '#C9C3B9']);
        $cats = [];
        $n = 0;
        $prices = [12.9, 24.5, 39.0, 59.0, 79.0, 119.0, 18.5, 45.0, 29.9, 9.9, 149.0, 65.0];
        foreach ($set as $ci => [$catName, $items]) {
            $r = Categories::save($db, ['name' => $catName, 'description' => 'Κατηγορία δείγμα: ' . $catName . '.', 'sort' => $ci]);
            $cid = $r['id'];
            $cats[] = ['id' => $cid, 'name' => $catName];
            foreach ($items as $name) {
                $price = $prices[$n % count($prices)];
                $media = self::image($db, (int) $shop['id'], $name, $palette[$n % count($palette)], $n);
                $media2 = self::image($db, (int) $shop['id'], $name . ' 2', $palette[($n + 2) % count($palette)], $n + 3);
                $res = Products::save($db, $shop, [
                    'name' => $name, 'sku' => 'DEMO-' . str_pad((string) ($n + 1), 3, '0', STR_PAD_LEFT), 'price' => $price, 'sale_price' => $n % 4 === 1 ? round($price * 0.85, 2) : null,
                    'stock' => 10 + $n * 3, 'manage_stock' => 1, 'availability' => 'in_stock', 'brand' => 'Μάρκα δείγμα',
                    'short_description' => '<p>Σύντομη περιγραφή για το «' . e($name) . '». Προϊόν δείγμα — αντικαταστήστε πριν τη δημοσίευση.</p>',
                    'description' => '<p>Πλήρης περιγραφή προϊόντος. Προϊόν δείγμα — αντικαταστήστε το με τα δικά σας προϊόντα.</p><h2>Χαρακτηριστικά</h2><ul><li>Χαρακτηριστικό 1</li><li>Χαρακτηριστικό 2</li></ul>',
                    'primary_category_id' => $cid, 'category_ids' => [$cid], 'featured' => $n % 3 === 0 ? 1 : 0, 'gallery' => array_filter([$media, $media2]),
                    'attributes' => [['name' => 'Υλικό', 'value' => 'Δείγμα'], ['name' => 'Χώρα', 'value' => 'Ελλάδα']],
                    'variants' => $n % 5 === 0 ? [['name' => 'S', 'stock' => 5], ['name' => 'M', 'stock' => 5], ['name' => 'L', 'stock' => 0]] : [],
                    'variant_option_names' => ['Μέγεθος'], 'is_active' => 1,
                ]);
                $n++;
            }
        }
        return $cats;
    }

    /** Generates a simple, tasteful placeholder product image (no text) so demo shops render nicely. */
    public static function image(PDO $db, int $shopId, string $name, string $hex, int $seed): ?int
    {
        if (!function_exists('imagecreatetruecolor')) return null;
        $w = 1200;
        $h = 1500;
        $im = imagecreatetruecolor($w, $h);
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($im, (int) min(255, $r + 18 * (1 - $t)), (int) min(255, $g + 18 * (1 - $t)), (int) min(255, $b + 18 * (1 - $t)));
            imageline($im, 0, $y, $w, $y, $c);
        }
        $ink = imagecolorallocatealpha($im, max(0, $r - 110), max(0, $g - 110), max(0, $b - 110), 10);
        $soft = imagecolorallocatealpha($im, 255, 255, 255, 80);
        $shadow = imagecolorallocatealpha($im, 0, 0, 0, 105);
        imagefilledellipse($im, (int) ($w / 2), 1260, 640, 70, $shadow);
        imagefilledellipse($im, 880, 300, 360, 360, $soft);
        switch ($seed % 5) {
            case 0: imagefilledrectangle($im, 380, 420, 820, 1230, $ink); imagefilledellipse($im, 600, 430, 440, 160, $ink); break;
            case 1: imagefilledellipse($im, 600, 820, 620, 620, $ink); break;
            case 2: imagefilledpolygon($im, [600, 380, 900, 1230, 300, 1230], $ink); break;
            case 3: imagefilledrectangle($im, 330, 640, 870, 1230, $ink); imagesetthickness($im, 34); imagearc($im, 600, 640, 360, 420, 180, 360, $ink); break;
            default: imagefilledrectangle($im, 470, 360, 730, 470, $ink); imagefilledrectangle($im, 360, 470, 840, 1230, $ink); break;
        }
        $tmp = ensure_dir(DATA_DIR . '/tmp') . '/demo-' . bin2hex(random_bytes(4)) . '.jpg';
        imagejpeg($im, $tmp, 88);
        imagedestroy($im);
        $res = ImageUpload::fromPath($db, $shopId, $tmp, slugify($name) . '.jpg', $name . ' (εικόνα δείγμα)');
        @unlink($tmp);
        return $res['ok'] ? (int) $res['media']['id'] : null;
    }
}
