<?php

namespace Nawasara\JobVacancy\Support;

/**
 * Self-contained EditorJS HTML sanitizer (English port of the loker renderer).
 *
 * Upstream content often carries raw HTML (e.g. `<b>`, `<a>` inside list
 * items). Instead of escaping everything (which would break formatting), the
 * renderer uses tag-whitelist sanitization so content stays formatted without
 * opening an XSS hole.
 */
class EditorJsRenderer
{
    /** Tags kept (attributes filtered further). */
    private const ALLOW_TAGS = ['a', 'b', 'strong', 'i', 'em', 'u', 's', 'del', 'mark', 'small',
        'sub', 'sup', 'br', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'img', 'hr'];

    /** Tags unwrapped (children kept, wrapper dropped). */
    private const UNWRAP_TAGS = ['p', 'div', 'span', 'font', 'center', 'section', 'article',
        'header', 'footer', 'main', 'aside', 'nav', 'figure', 'figcaption', 'table',
        'thead', 'tbody', 'tr', 'td', 'th', 'caption'];

    /** Dangerous tags — element and its content removed. */
    private const BLOCK_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form',
        'input', 'textarea', 'select', 'button', 'link', 'meta', 'base', 'noscript',
        'template', 'title'];

    public static function sanitizeUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '#';
        }

        if (! preg_match('#^(?:https?:|mailto:|tel:|\/|\#|\.)#i', $url)) {
            return '#';
        }

        return preg_replace('/[\x00-\x20"\'<>]/', '', $url) ?? '#';
    }

    /**
     * Clean an HTML fragment: drop dangerous elements (and content), unwrap
     * structural wrappers, whitelist attributes, block event handlers and
     * `javascript:`/`data:` URLs. Output is safe to render via `{!! !!}`.
     */
    public static function sanitizeHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // loadHTML failed (not well-formed) → fail safe: full escape.
        if (! $loaded) {
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        }

        $xpath = new \DOMXPath($dom);

        foreach (iterator_to_array($xpath->query('//comment()')) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $nodes = iterator_to_array($xpath->query('//*'));
        $nodes = array_reverse($nodes); // reverse document order → safe to unwrap

        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($node->nodeName);

            if (in_array($tag, self::BLOCK_TAGS, true)) {
                $node->parentNode?->removeChild($node);
                continue;
            }

            if (in_array($tag, self::UNWRAP_TAGS, true)) {
                static::unwrap($node);
                continue;
            }

            if (! in_array($tag, self::ALLOW_TAGS, true)) {
                static::unwrap($node);
                continue;
            }

            static::sanitizeAttributes($node, $tag);
        }

        $out = $dom->saveHTML();
        $out = preg_replace([
            '~^<\?xml[^>]*\?>~i',
            '~^<!DOCTYPE[^>]*>~i',
            '~^<html[^>]*>|</html>$~i',
            '~^<body[^>]*>|</body>$~i',
        ], '', $out) ?? '';

        // Normalize spacing between inline elements ('</b><a>' → '</b> <a>').
        $out = preg_replace(
            '~(</(?:a|b|strong|i|em|u|s|del|mark|small|sub|sup|code)>)\s*(<(?:a|b|strong|i|em|u|s|del|mark|small|sub|sup|code)\b)~',
            '$1 $2',
            $out
        ) ?? $out;

        return trim($out);
    }

    private static function unwrap(\DOMElement $node): void
    {
        $parent = $node->parentNode;
        if (! $parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    private static function sanitizeAttributes(\DOMElement $node, string $tag): void
    {
        $perTag = $tag === 'a'
            ? ['href', 'title', 'target']
            : ($tag === 'img' ? ['src', 'alt', 'width', 'height'] : []);

        foreach (iterator_to_array($node->attributes) as $attr) {
            $name = strtolower($attr->nodeName);

            if (str_starts_with($name, 'on')) {
                $node->removeAttributeNode($attr);
                continue;
            }

            if (! in_array($name, $perTag, true)) {
                $node->removeAttributeNode($attr);
                continue;
            }

            if (in_array($name, ['href', 'src'], true)) {
                $value = strtolower(ltrim($attr->value));

                if (preg_match('~(?:javascript|vbscript|data)\s*:~i', $value)
                    || self::sanitizeUrl($attr->value) === '#') {
                    $node->removeAttribute($name);
                    continue;
                }
            }
        }

        if ($tag === 'a') {
            $target = strtolower($node->getAttribute('target'));
            if (! in_array($target, ['_blank', '_self', '_top', '_parent'], true)) {
                $node->removeAttribute('target');
            }
            $node->setAttribute('rel', 'nofollow noopener');
        }
    }
}