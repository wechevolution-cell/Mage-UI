<?php
declare(strict_types=1);

/** Tenant settings with defaults. All templates read the same structure. */
final class ShopSettings
{
    public static function defaults(): array
    {
        return [
            'store' => ['name' => '', 'company_name' => '', 'vat_number' => '', 'tax_office' => '', 'email' => '', 'phone' => '', 'address' => '', 'city' => '', 'postcode' => '',
                'hours' => '', 'map_url' => '', 'about_short' => '',
                'socials' => ['facebook' => '', 'instagram' => '', 'tiktok' => '', 'youtube' => '', 'linkedin' => '', 'x' => '']],
            'appearance' => ['logo_id' => null, 'favicon_id' => null, 'tokens' => [], 'font' => 'default', 'logo_text' => ''],
            'homepage' => self::homepageDefaults(),
            'shipping' => [
                'zones' => [
                    'mainland' => ['label' => 'Ηπειρωτική Ελλάδα', 'cost' => 3.5, 'enabled' => true],
                    'islands' => ['label' => 'Νησιά', 'cost' => 5.0, 'enabled' => true],
                    'cyprus' => ['label' => 'Κύπρος', 'cost' => 12.0, 'enabled' => false],
                ],
                'free_threshold' => 50.0,
                'pickup' => ['enabled' => false, 'label' => 'Παραλαβή από το κατάστημα', 'address' => ''],
                'delivery_text' => '1–3 εργάσιμες ημέρες',
            ],
            'payments' => [
                'cod' => ['enabled' => true, 'label' => 'Αντικαταβολή', 'fee' => 2.0, 'instructions' => 'Πληρωμή με μετρητά ή κάρτα στον courier.'],
                'bank' => ['enabled' => true, 'label' => 'Τραπεζική κατάθεση', 'fee' => 0.0, 'instructions' => "Τράπεζα: —\nIBAN: —\nΔικαιούχος: —\nΑιτιολογία: αριθμός παραγγελίας"],
            ],
            'checkout' => ['order_prefix' => 'ORD', 'gift_wrap_enabled' => false, 'gift_wrap_price' => 2.0, 'min_order' => 0.0, 'invoice_enabled' => true, 'guest_checkout' => true],
            'tax' => ['prices_include_vat' => true, 'vat_rate' => 24.0],
            'seo' => ['site_title' => '', 'title_suffix' => '', 'default_description' => '', 'ga4_id' => '', 'gsc_verification' => '', 'allow_ai_bots' => true,
                'robots_extra' => '', 'org_type' => 'OnlineStore', 'llms_intro' => '', 'default_og_image_id' => null],
            'notifications' => ['order_email' => '', 'notify_admin_new_order' => true, 'notify_customer' => true],
            'cookies' => ['banner_text' => 'Χρησιμοποιούμε απαραίτητα cookies για τη λειτουργία του καταστήματος και, με τη συγκατάθεσή σας, cookies στατιστικών (Google Analytics).'],
            'onboarding' => ['dismissed' => false],
        ];
    }

    public static function homepageDefaults(): array
    {
        return [
            'order' => ['hero', 'benefits', 'categories', 'featured', 'banners', 'story', 'newest', 'brands', 'newsletter'],
            'hero' => ['enabled' => true, 'eyebrow' => 'Νέα συλλογή', 'title' => 'Καλώς ήρθατε στο κατάστημά μας', 'subtitle' => 'Ανακαλύψτε προϊόντα επιλεγμένα με φροντίδα. Κείμενο δείγμα — αλλάξτε το από το Homepage CMS.',
                'cta_text' => 'Δείτε τα προϊόντα', 'cta_url' => '/products/', 'cta2_text' => '', 'cta2_url' => '', 'image_id' => null],
            'benefits' => ['enabled' => true, 'items' => [
                ['icon' => 'truck', 'title' => 'Αποστολή', 'text' => 'Γρήγορη αποστολή σε όλη την Ελλάδα'],
                ['icon' => 'return', 'title' => 'Επιστροφές', 'text' => 'Εύκολες επιστροφές (δείτε την πολιτική)'],
                ['icon' => 'card', 'title' => 'Πληρωμή', 'text' => 'Αντικαταβολή ή τραπεζική κατάθεση'],
                ['icon' => 'shield', 'title' => 'Ασφάλεια', 'text' => 'Ασφαλείς αγορές'],
            ]],
            'categories' => ['enabled' => true, 'title' => 'Κατηγορίες', 'ids' => []],
            'featured' => ['enabled' => true, 'title' => 'Προτεινόμενα προϊόντα', 'count' => 8],
            'newest' => ['enabled' => true, 'title' => 'Νέες αφίξεις', 'count' => 8],
            'banners' => ['enabled' => false, 'items' => [
                ['title' => '', 'text' => '', 'cta' => '', 'url' => '', 'image_id' => null],
                ['title' => '', 'text' => '', 'cta' => '', 'url' => '', 'image_id' => null],
            ]],
            'story' => ['enabled' => false, 'eyebrow' => 'Η ιστορία μας', 'title' => '', 'text' => '', 'cta_text' => '', 'cta_url' => '', 'image_id' => null],
            'brands' => ['enabled' => false, 'title' => 'Μάρκες', 'items' => []],
            'newsletter' => ['enabled' => true, 'title' => 'Εγγραφείτε στο newsletter', 'text' => 'Μάθετε πρώτοι για νέα προϊόντα και προσφορές.'],
        ];
    }

    /** Deep-merged settings group. */
    public static function get(PDO $db, string $group): array
    {
        $d = self::defaults()[$group] ?? [];
        $v = setting_get($db, $group, []);
        return is_array($v) ? array_replace_recursive($d, $v) : $d;
    }

    public static function set(PDO $db, string $group, array $value): void { setting_set($db, $group, $value); }

    /** Store name helper */
    public static function storeName(PDO $db, ?array $shop = null): string
    {
        $n = self::get($db, 'store')['name'] ?? '';
        return $n !== '' ? $n : ($shop['name'] ?? 'E-shop');
    }

    /** Effective design tokens = template defaults overlaid with the shop's safe overrides. */
    public static function tokens(PDO $db, string $templateKey): array
    {
        $base = Templates::tokens($templateKey);
        $over = (array) (self::get($db, 'appearance')['tokens'] ?? []);
        foreach ($over as $k => $v) {
            if (isset($base[$k]) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v)) $base[$k] = $v;
        }
        return $base;
    }

    public const TOKEN_LABELS = [
        'primary' => 'Κύριο χρώμα', 'secondary' => 'Δευτερεύον χρώμα', 'accent' => 'Χρώμα έμφασης', 'background' => 'Φόντο', 'surface' => 'Φόντο καρτών',
        'text' => 'Κείμενο', 'muted' => 'Δευτερεύον κείμενο', 'button_bg' => 'Φόντο κουμπιών', 'button_text' => 'Κείμενο κουμπιών', 'link' => 'Σύνδεσμοι',
    ];
}
