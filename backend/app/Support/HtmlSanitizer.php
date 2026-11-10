<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML sanitizer for admin-authored rich text (Summernote output).
 *
 * The project has no HTML purifier package installed (see the stopgap in
 * PageRendererService::sanitizeHtml()), so this walks the parsed DOM and keeps
 * only known-safe tags, attributes, URL schemes and inline-style properties.
 * Unknown tags are unwrapped (their text is kept); dangerous containers such as
 * <script>, <style> and <iframe> are dropped together with their content.
 */
class HtmlSanitizer
{
    /** @var array<string, list<string>> tag => tag-specific allowed attributes */
    private const ALLOWED_TAGS = [
        'p' => [], 'br' => [], 'hr' => [], 'div' => [], 'span' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 's' => [], 'strike' => [], 'del' => [],
        'sub' => [], 'sup' => [], 'small' => [], 'mark' => [],
        'ul' => [], 'ol' => ['start', 'type'], 'li' => [],
        'blockquote' => [], 'pre' => [], 'code' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'], 'caption' => [],
    ];

    /** Attributes allowed on every allowed tag. */
    private const GLOBAL_ATTRIBUTES = ['dir', 'style'];

    /** Removed together with everything inside them. */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input',
        'button', 'select', 'option', 'textarea', 'svg', 'math', 'template', 'noscript', 'link', 'meta',
        'base', 'head', 'title', 'audio', 'video', 'source', 'canvas',
    ];

    /** Inline CSS properties kept from `style` (Summernote alignment, image sizing, colors). */
    private const ALLOWED_STYLES = [
        'text-align', 'direction', 'width', 'height', 'max-width', 'float',
        'font-weight', 'font-style', 'text-decoration', 'color', 'background-color',
    ];

    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>'
            .'<body>'.$html.'</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // The <body> itself is the root (not a wrapper <div>), so a stray "</div>" in the input cannot
        // close the root early and silently drop everything after it.
        $root = $document->getElementsByTagName('body')->item(0);
        if (! $root) {
            return null;
        }

        static::sanitizeChildren($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        $output = trim($output);

        return $output === '' ? null : $output;
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (! $node instanceof DOMElement) {
                // Comments, CDATA, processing instructions.
                $parent->removeChild($node);

                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }

            static::sanitizeChildren($node);

            if (! array_key_exists($tag, self::ALLOWED_TAGS)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            static::sanitizeAttributes($node, $tag);
        }
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = [...self::GLOBAL_ATTRIBUTES, ...self::ALLOWED_TAGS[$tag]];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = trim($attribute->nodeValue ?? '');

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            $clean = match ($name) {
                'href' => static::safeUrl($value, allowAnchors: true),
                'src' => static::safeUrl($value, allowAnchors: false),
                'style' => static::safeStyle($value),
                'dir' => in_array(strtolower($value), ['rtl', 'ltr', 'auto'], true) ? strtolower($value) : null,
                'target' => in_array($value, ['_blank', '_self'], true) ? $value : null,
                'width', 'height', 'colspan', 'rowspan', 'start' => preg_match('/^\d{1,4}(%|px)?$/', $value) ? $value : null,
                default => $value,
            };

            if ($clean === null || $clean === '') {
                $element->removeAttribute($attribute->nodeName);
            } else {
                $element->setAttribute($name, $clean);
            }
        }

        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /** Absolute http(s)/mailto/tel URLs, or relative paths / anchors (no scheme). */
    private static function safeUrl(string $url, bool $allowAnchors): ?string
    {
        // Strip control characters and whitespace that browsers ignore inside schemes ("java\tscript:").
        $normalized = preg_replace('/[\x00-\x20\x7F]+/u', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalized, $matches)) {
            return in_array(strtolower($matches[1]), self::ALLOWED_SCHEMES, true) ? $url : null;
        }

        if (str_starts_with($normalized, '//')) {
            return null;
        }

        if (! $allowAnchors && str_starts_with($normalized, '#')) {
            return null;
        }

        return $url;
    }

    private static function safeStyle(string $style): ?string
    {
        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);

            if (in_array($property, self::ALLOWED_STYLES, true) && preg_match('/^[#\w\s.,%()\-]+$/u', $value) && ! preg_match('/(url|expression)\s*\(/i', $value)) {
                $kept[] = "{$property}: {$value}";
            }
        }

        return $kept === [] ? null : implode('; ', $kept);
    }
}
