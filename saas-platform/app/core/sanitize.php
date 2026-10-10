<?php
declare(strict_types=1);

/**
 * Strict allow-list HTML sanitizer for rich text from admins and imports.
 * Removes scripts, event handlers, javascript:/data: URLs; iframes only from YouTube, Vimeo, Google Maps.
 */
final class Sanitizer
{
    private const TAGS = [
        'p' => [], 'br' => [], 'hr' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'small' => [], 'sub' => [], 'sup' => [], 'mark' => [],
        'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'h5' => [], 'h6' => [],
        'ul' => [], 'ol' => ['start'], 'li' => [], 'dl' => [], 'dt' => [], 'dd' => [],
        'blockquote' => ['cite'], 'pre' => [], 'code' => [], 'figure' => [], 'figcaption' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'title', 'loading'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'], 'caption' => [],
        'span' => [], 'div' => [], 'section' => [], 'details' => [], 'summary' => [],
        'iframe' => ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'loading'],
    ];
    private const IFRAME_HOSTS = ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com', 'www.google.com', 'maps.google.com'];
    private const DROP_WITH_CONTENT = ['script', 'style', 'object', 'embed', 'applet', 'form', 'input', 'button', 'select', 'textarea', 'noscript', 'template', 'svg', 'math', 'link', 'meta', 'base', 'head', 'title'];

    public static function html(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') return '';
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('__root') ?: $doc->documentElement;
        if (!$root) return '';
        self::clean($root);
        $out = '';
        foreach (iterator_to_array($root->childNodes) as $c) { $out .= $doc->saveHTML($c); }
        return trim($out);
    }

    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction || $child instanceof DOMCdataSection) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) continue;
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) { $node->removeChild($child); continue; }
            if (!array_key_exists($tag, self::TAGS)) {
                self::clean($child);
                while ($child->firstChild) { $node->insertBefore($child->firstChild, $child); }
                $node->removeChild($child);
                continue;
            }
            $allowed = self::TAGS[$tag];
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $allowed, true)) { $child->removeAttribute($attr->name); continue; }
                if (in_array($name, ['href', 'src', 'cite'], true) && !self::safeUrl($attr->value, $name === 'src')) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'iframe') {
                $host = strtolower((string) parse_url($child->getAttribute('src'), PHP_URL_HOST));
                $ok = in_array($host, self::IFRAME_HOSTS, true) && ($host !== 'www.google.com' || str_starts_with((string) parse_url($child->getAttribute('src'), PHP_URL_PATH), '/maps'));
                if (!$ok || !str_starts_with($child->getAttribute('src'), 'https://')) { $node->removeChild($child); continue; }
                $child->setAttribute('loading', 'lazy');
            }
            if ($tag === 'a') {
                if ($child->getAttribute('target') === '_blank') { $child->setAttribute('rel', 'noopener noreferrer'); }
                elseif ($child->hasAttribute('target')) { $child->removeAttribute('target'); }
            }
            if ($tag === 'img') {
                if (!$child->hasAttribute('src')) { $node->removeChild($child); continue; }
                $child->setAttribute('loading', 'lazy');
            }
            self::clean($child);
        }
    }

    public static function safeUrl(string $url, bool $isSrc = false): bool
    {
        $u = trim(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($u === '') return false;
        if (preg_match('#^(https?:)?//#i', $u) || str_starts_with($u, '/') || str_starts_with($u, '#') || str_starts_with($u, '?')) return true;
        if (!$isSrc && preg_match('#^(mailto|tel):#i', $u)) return true;
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $u)) return false; // javascript:, data:, vbscript: …
        return true; // relative path
    }

    public static function text(?string $s, int $max = 10000): string
    {
        return mb_substr(trim(strip_tags(str_replace("\0", '', (string) $s))), 0, $max);
    }

    public static function url(?string $u): string
    {
        $u = trim((string) $u);
        return ($u !== '' && self::safeUrl($u)) ? mb_substr($u, 0, 2000) : '';
    }

    public static function color(?string $c, string $fallback): string
    {
        $c = trim((string) $c);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtoupper($c) : $fallback;
    }
}
