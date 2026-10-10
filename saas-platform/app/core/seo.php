<?php
declare(strict_types=1);

/** Collects per-page SEO meta + JSON-LD @graph nodes and renders them in <head>. */
final class Seo
{
    public static array $meta = [];
    public static array $graph = [];
    public static array $crumbs = [];

    public static function reset(): void { self::$meta = []; self::$graph = []; self::$crumbs = []; }

    public static function set(array $m): void { self::$meta = array_merge(self::$meta, array_filter($m, fn($v) => $v !== null && $v !== '')); }

    public static function add(array $node): void { self::$graph[] = $node; }

    public static function crumbs(array $items): void { self::$crumbs = $items; }

    public static function head(): string
    {
        $m = self::$meta;
        $title = $m['title'] ?? '';
        $desc = $m['description'] ?? '';
        $canon = $m['canonical'] ?? null;
        $robots = $m['robots'] ?? 'index, follow, max-image-preview:large';
        $h = '<title>' . e($title) . "</title>\n";
        if ($desc) $h .= '<meta name="description" content="' . e(mb_substr($desc, 0, 300)) . "\">\n";
        $h .= '<meta name="robots" content="' . e($robots) . "\">\n";
        if ($canon) $h .= '<link rel="canonical" href="' . e($canon) . "\">\n";
        $h .= '<link rel="alternate" hreflang="el" href="' . e($canon ?? abs_url(req_path())) . "\">\n";
        $og = ['og:type' => $m['og_type'] ?? 'website', 'og:title' => $m['og_title'] ?? $title, 'og:description' => $desc, 'og:url' => $canon,
            'og:site_name' => $m['site_name'] ?? null, 'og:locale' => 'el_GR', 'og:image' => $m['image'] ?? null];
        foreach ($og as $k => $v) { if ($v) $h .= '<meta property="' . $k . '" content="' . e($v) . "\">\n"; }
        if (!empty($m['price'])) {
            $h .= '<meta property="product:price:amount" content="' . e($m['price']) . "\">\n<meta property=\"product:price:currency\" content=\"EUR\">\n";
        }
        $h .= '<meta name="twitter:card" content="' . (!empty($m['image']) ? 'summary_large_image' : 'summary') . "\">\n";
        $h .= '<meta name="twitter:title" content="' . e($title) . "\">\n";
        if ($desc) $h .= '<meta name="twitter:description" content="' . e(mb_substr($desc, 0, 200)) . "\">\n";
        if (!empty($m['image'])) $h .= '<meta name="twitter:image" content="' . e($m['image']) . "\">\n";

        $graph = self::$graph;
        if (self::$crumbs) {
            $items = [];
            foreach (array_values(self::$crumbs) as $i => [$name, $url]) {
                $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => abs_url($url)];
            }
            $graph[] = ['@type' => 'BreadcrumbList', '@id' => abs_url(req_path()) . '#breadcrumb', 'itemListElement' => $items];
        }
        if ($graph) {
            $h .= '<script type="application/ld+json">' . ej(['@context' => 'https://schema.org', '@graph' => $graph]) . "</script>\n";
        }
        return $h;
    }

    /** FAQ array [{q,a}] → FAQPage node */
    public static function faqNode(array $faq, string $url): ?array
    {
        $faq = array_values(array_filter($faq, fn($f) => !empty($f['q']) && !empty($f['a'])));
        if (!$faq) return null;
        return ['@type' => 'FAQPage', '@id' => $url . '#faq', 'mainEntity' => array_map(fn($f) => [
            '@type' => 'Question', 'name' => strip_tags($f['q']), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($f['a']))],
        ], $faq)];
    }
}
