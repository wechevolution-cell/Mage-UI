<?php
declare(strict_types=1);

/** Live SEO (0–100) + AEO (0–100) content analyzer used by the admin editors via AJAX. */
final class SeoAnalyzer
{
    /**
     * @param array{title?:string,seo_title?:string,seo_description?:string,slug?:string,content?:string,focus_keyword?:string,faq?:array,images?:int,robots?:string,type?:string,tldr?:string,sources?:string,author?:string} $in
     */
    public static function analyze(array $in): array
    {
        $kw = str_fold(trim((string) ($in['focus_keyword'] ?? '')));
        $title = (string) (($in['seo_title'] ?? '') ?: ($in['title'] ?? ''));
        $desc = (string) ($in['seo_description'] ?? '');
        $html = (string) ($in['content'] ?? '');
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
        $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text));
        $foldText = str_fold($text);
        $first = str_fold(mb_substr($text, 0, 300));
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/isu', $html, $h2);
        preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/isu', $html, $h23);
        preg_match_all('/<a\s[^>]*href="([^"]+)"/iu', $html, $links);
        $internal = count(array_filter($links[1], fn($h) => str_starts_with($h, '/') || str_contains($h, host_without_port(req_host()))));
        $external = count($links[1]) - $internal;
        preg_match_all('/<img\s[^>]*>/iu', $html, $imgs);
        $imgNoAlt = count(array_filter($imgs[0], fn($i) => !preg_match('/alt="[^"]+"/', $i)));
        $density = ($kw !== '' && $words > 0) ? substr_count($foldText, $kw) * max(1, count(explode(' ', $kw))) / $words * 100 : 0;
        $type = $in['type'] ?? 'page';
        $minWords = $type === 'product' ? 120 : ($type === 'category' ? 80 : 300);

        $seo = [];
        $add = function (array &$list, string $label, bool $pass, int $weight, string $hint = '') { $list[] = ['label' => $label, 'pass' => $pass, 'weight' => $weight, 'hint' => $hint]; };
        $add($seo, 'Λέξη-κλειδί εστίασης ορίστηκε', $kw !== '', 10, 'Ορίστε μια κύρια λέξη-κλειδί.');
        $add($seo, 'Λέξη-κλειδί στον τίτλο SEO', $kw !== '' && str_contains(str_fold($title), $kw), 12);
        $tl = mb_strlen($title);
        $add($seo, "Μήκος τίτλου 30–60 χαρακτήρες ($tl)", $tl >= 30 && $tl <= 60, 10);
        $dl = mb_strlen($desc);
        $add($seo, "Meta description 120–160 χαρακτήρες ($dl)", $dl >= 120 && $dl <= 160, 10);
        $add($seo, 'Λέξη-κλειδί στη meta description', $kw !== '' && str_contains(str_fold($desc), $kw), 6);
        $add($seo, 'Λέξη-κλειδί στο slug', $kw !== '' && str_contains((string) ($in['slug'] ?? ''), slugify($kw)), 6);
        $add($seo, 'Λέξη-κλειδί στην εισαγωγή', $kw !== '' && str_contains($first, $kw), 8);
        $add($seo, 'Υπάρχουν επικεφαλίδες H2', count($h2[0]) > 0, 6);
        $add($seo, sprintf('Πυκνότητα λέξης-κλειδιού 0,5–2,5%% (%.1f%%)', $density), $density >= 0.5 && $density <= 2.5, 6);
        $add($seo, "Μήκος κειμένου ≥ $minWords λέξεις ($words)", $words >= $minWords, 10);
        $add($seo, "Εσωτερικοί σύνδεσμοι ($internal)", $internal > 0, 6);
        $add($seo, "Εξωτερικοί σύνδεσμοι ($external)", $external > 0, 2);
        $add($seo, 'Εικόνες με alt text', $imgNoAlt === 0, 4, "$imgNoAlt εικόνες χωρίς alt");
        $add($seo, 'Η σελίδα είναι indexable', !str_contains((string) ($in['robots'] ?? ''), 'noindex'), 4);

        $aeo = [];
        $faq = array_filter((array) ($in['faq'] ?? []), fn($f) => !empty($f['q']));
        $questions = count(array_filter($h23[1], fn($h) => str_contains(strip_tags($h), '?') || str_contains(strip_tags($h), ';')));
        $add($aeo, 'Σύνοψη TL;DR / σύντομη απάντηση', trim((string) ($in['tldr'] ?? '')) !== '' || mb_strlen($desc) >= 80, 15);
        $add($aeo, 'Ενότητα FAQ (' . count($faq) . ')', count($faq) >= 2, 20);
        $add($aeo, "Επικεφαλίδες σε μορφή ερώτησης ($questions)", $questions > 0 || count($faq) > 0, 10);
        $add($aeo, 'Λίστες (ul/ol)', (bool) preg_match('/<(ul|ol)\b/i', $html), 10);
        $add($aeo, 'Πίνακες', (bool) preg_match('/<table\b/i', $html) || $type === 'product', 10);
        $add($aeo, 'Η πρώτη παράγραφος απαντά άμεσα (≤ 60 λέξεις)', $words > 0 && count(preg_split('/\s+/u', excerpt(preg_match('/<p[^>]*>(.*?)<\/p>/isu', $html, $pm) ? $pm[1] : $text, 600))) <= 60, 10);
        $add($aeo, 'Συντάκτης / πηγή αναφέρεται', trim((string) ($in['author'] ?? '')) !== '' || $type !== 'post', 10);
        $add($aeo, 'Πηγές / παραπομπές', trim((string) ($in['sources'] ?? '')) !== '' || $external > 0, 5);
        $add($aeo, 'Τύπος Schema (JSON-LD) αυτόματα', true, 10);

        $score = fn(array $l) => (int) round(array_sum(array_map(fn($c) => $c['pass'] ? $c['weight'] : 0, $l)) / max(1, array_sum(array_column($l, 'weight'))) * 100);
        return ['seo' => $score($seo), 'aeo' => $score($aeo), 'seo_checks' => $seo, 'aeo_checks' => $aeo, 'words' => $words];
    }

    /** Keyword cannibalization: other entities with overlapping focus keywords (accent/case-insensitive). */
    public static function cannibalization(PDO $db, string $keyword, string $type, ?int $exceptId): array
    {
        $kw = str_fold(trim($keyword));
        if ($kw === '') return [];
        $tokens = array_filter(explode(' ', $kw), fn($t) => mb_strlen($t) > 2);
        $out = [];
        $check = function (string $t, string $label, string $sql) use ($db, $kw, $tokens, $type, $exceptId, &$out) {
            foreach (db_all($db, $sql) as $r) {
                if ($t === $type && (int) $r['id'] === (int) $exceptId) continue;
                $other = str_fold((string) $r['kw']);
                if ($other === '') continue;
                $ot = array_filter(explode(' ', $other), fn($x) => mb_strlen($x) > 2);
                $overlap = $tokens && $ot ? count(array_intersect($tokens, $ot)) / max(count($tokens), count($ot)) : 0;
                if ($other === $kw || $overlap >= 0.6) $out[] = ['type' => $label, 'id' => (int) $r['id'], 'title' => $r['title'], 'keyword' => $r['kw']];
            }
        };
        $check('product', 'Προϊόν', "SELECT id, name AS title, focus_keyword AS kw FROM products WHERE deleted_at IS NULL AND focus_keyword IS NOT NULL AND focus_keyword <> ''");
        $check('post', 'Άρθρο', "SELECT id, title, focus_keyword AS kw FROM posts WHERE deleted_at IS NULL AND focus_keyword IS NOT NULL AND focus_keyword <> ''");
        return $out;
    }
}
