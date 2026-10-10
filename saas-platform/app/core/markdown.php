<?php
declare(strict_types=1);

/**
 * Small Markdown → HTML converter (headings, lists, tables, blockquotes, code, links, emphasis).
 * FAQ syntax: a heading named "FAQ" / "Συχνές ερωτήσεις", followed by "### Question" + answer paragraphs,
 * is extracted into a repeatable FAQ array.
 */
final class Markdown
{
    /** @return array{html:string, faq:array<int,array{q:string,a:string}>, h1:?string} */
    public static function convert(string $md): array
    {
        $md = str_replace(["\r\n", "\r"], "\n", $md);
        $faq = [];
        $h1 = null;
        if (preg_match('/^#\s+(.+)$/m', $md, $m)) { $h1 = trim($m[1]); $md = preg_replace('/^#\s+.+$\n?/m', '', $md, 1); }
        // extract FAQ section
        if (preg_match('/^(#{2,3})\s*(FAQ|Συχνές ερωτήσεις|Συχνές Ερωτήσεις|Ερωτήσεις)\s*$/mu', $md, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
            $level = strlen($m[1][0]);
            $rest = substr($md, $start + strlen($m[0][0]));
            $end = preg_match('/^#{2,' . $level . '}\s+(?!#)/m', $rest, $mm, PREG_OFFSET_CAPTURE) ? $mm[0][1] : strlen($rest);
            $section = substr($rest, 0, $end);
            preg_match_all('/^#{3,4}\s+(.+)\n(.*?)(?=^#{3,4}\s+|\z)/msu', $section, $qs, PREG_SET_ORDER);
            foreach ($qs as $q) {
                $a = trim($q[2]);
                if ($a !== '') $faq[] = ['q' => trim($q[1]), 'a' => self::blocks($a)];
            }
            if ($faq) $md = substr($md, 0, $start) . substr($rest, $end);
        }
        return ['html' => Sanitizer::html(self::blocks($md)), 'faq' => $faq, 'h1' => $h1];
    }

    public static function blocks(string $md): string
    {
        $lines = explode("\n", $md);
        $out = [];
        $i = 0;
        $n = count($lines);
        while ($i < $n) {
            $line = $lines[$i];
            if (trim($line) === '') { $i++; continue; }
            if (preg_match('/^```/', $line)) {
                $code = [];
                $i++;
                while ($i < $n && !preg_match('/^```/', $lines[$i])) { $code[] = $lines[$i]; $i++; }
                $i++;
                $out[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/', $line, $m)) {
                $lvl = max(2, min(4, strlen($m[1])));
                $text = trim($m[2]);
                $out[] = "<h$lvl id=\"" . e(slugify($text, 60)) . "\">" . self::inline($text) . "</h$lvl>";
                $i++;
                continue;
            }
            if (preg_match('/^(-{3,}|\*{3,})$/', trim($line))) { $out[] = '<hr>'; $i++; continue; }
            if (str_starts_with(ltrim($line), '>')) {
                $q = [];
                while ($i < $n && str_starts_with(ltrim($lines[$i]), '>')) { $q[] = preg_replace('/^\s*>\s?/', '', $lines[$i]); $i++; }
                $out[] = '<blockquote>' . self::blocks(implode("\n", $q)) . '</blockquote>';
                continue;
            }
            if (str_contains($line, '|') && $i + 1 < $n && preg_match('/^\s*\|?\s*:?-{2,}/', $lines[$i + 1])) {
                $head = self::row($line);
                $i += 2;
                $rows = [];
                while ($i < $n && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') { $rows[] = self::row($lines[$i]); $i++; }
                $h = '<table><thead><tr>' . implode('', array_map(fn($c) => '<th>' . self::inline($c) . '</th>', $head)) . '</tr></thead><tbody>';
                foreach ($rows as $r) { $h .= '<tr>' . implode('', array_map(fn($c) => '<td>' . self::inline($c) . '</td>', $r)) . '</tr>'; }
                $out[] = $h . '</tbody></table>';
                continue;
            }
            if (preg_match('/^\s*([-*+]|\d+[.)])\s+/', $line, $m)) {
                $ordered = ctype_digit(rtrim($m[1], '.)'));
                $items = [];
                while ($i < $n && preg_match('/^\s*([-*+]|\d+[.)])\s+(.*)$/', $lines[$i], $mm)) { $items[] = $mm[2]; $i++; }
                $tag = $ordered ? 'ol' : 'ul';
                $out[] = "<$tag>" . implode('', array_map(fn($t) => '<li>' . self::inline($t) . '</li>', $items)) . "</$tag>";
                continue;
            }
            $p = [];
            while ($i < $n && trim($lines[$i]) !== '' && !preg_match('/^(#{1,6}\s|```|>|\s*([-*+]|\d+[.)])\s)/', $lines[$i])) { $p[] = trim($lines[$i]); $i++; }
            if ($p) $out[] = '<p>' . self::inline(implode(' ', $p)) . '</p>';
            else $i++;
        }
        return implode("\n", $out);
    }

    private static function row(string $l): array
    {
        $l = trim($l);
        $l = trim($l, '|');
        return array_map('trim', explode('|', $l));
    }

    public static function inline(string $t): string
    {
        $t = e($t);
        $t = preg_replace('/`([^`]+)`/', '<code>$1</code>', $t);
        $t = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', fn($m) => Sanitizer::safeUrl(html_entity_decode($m[2]), true) ? '<img src="' . $m[2] . '" alt="' . $m[1] . '">' : $m[1], $t);
        $t = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', fn($m) => Sanitizer::safeUrl(html_entity_decode($m[2])) ? '<a href="' . $m[2] . '">' . $m[1] . '</a>' : $m[1], $t);
        $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t);
        $t = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '<em>$1</em>', $t);
        $t = preg_replace('/(?<![\w_])_(?!\s)(.+?)(?<!\s)_(?![\w_])/u', '<em>$1</em>', $t);
        return $t;
    }
}
