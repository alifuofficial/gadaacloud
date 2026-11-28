<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Enterprise allowlist HTML sanitizer & plain-text converter for Tiptap rich-text fields.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'del',
        'sub', 'sup', 'mark', 'code', 'pre', 'blockquote', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'div',
        'ul', 'ol', 'li', 'label', 'input',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'colgroup', 'col',
        'a', 'img',
    ];

    private const DROP_WITH_CONTENT_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math',
        'form', 'textarea', 'select', 'option', 'button', 'meta', 'link', 'base',
    ];

    /**
     * Sanitize untrusted HTML while preserving full Tiptap formatting (tables, checklists, links, images).
     */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $trimmed = trim($html);
        if ($trimmed === '' || self::isVisuallyEmptyHtml($trimmed)) {
            return null;
        }

        // Plain text without HTML markup is preserved safely without double-encoding
        if (!str_contains($trimmed, '<')) {
            return $trimmed;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $wrapped = '<?xml encoding="utf-8" ?><div id="gadaa-html-root">' . $trimmed . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('gadaa-html-root');
        if (!$root) {
            $fallback = trim(strip_tags($trimmed));
            return $fallback === '' ? null : htmlspecialchars($fallback, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        self::sanitizeNode($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        $output = trim($output);
        if ($output === '' || self::isVisuallyEmptyHtml($output)) {
            return null;
        }

        return $output;
    }

    /**
     * Convert HTML content into clean plain text (ideal for tables, Kanban snippets, search, CSV/Excel exports).
     */
    public static function toPlainText(?string $html, ?int $maxLength = null): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $spaced = preg_replace('/<\/(p|div|li|tr|h[1-6]|blockquote|pre)>/i', "\n", $html);
        $spaced = preg_replace('/<br\s*\/?>/i', "\n", (string) $spaced);
        $plain = html_entity_decode(strip_tags((string) $spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = trim((string) preg_replace("/[ \t]+/", ' ', (string) preg_replace("/\n{2,}/", "\n", $plain)));

        if ($maxLength !== null && $maxLength > 0 && mb_strlen($plain) > $maxLength) {
            return rtrim(mb_substr($plain, 0, $maxLength)) . '...';
        }

        return $plain;
    }

    /**
     * Detect empty Tiptap editor markup such as "<p></p>" or whitespace-only paragraphs.
     */
    public static function isVisuallyEmptyHtml(string $html): bool
    {
        if (preg_match('/<img\b|<hr\b/i', $html)) {
            return false;
        }

        $stripped = trim(str_replace(["\xc2\xa0", '&nbsp;'], '', strip_tags($html)));

        return $stripped === '';
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT_TAGS, true)) {
                $node->removeChild($child);
                continue;
            }

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                self::sanitizeNode($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            // Restrict <input> strictly to inert checkboxes inside task lists
            if ($tag === 'input' && strtolower((string) $child->getAttribute('type')) !== 'checkbox') {
                $node->removeChild($child);
                continue;
            }

            $attrsToRemove = [];
            $sanitizedStyle = null;

            foreach ($child->attributes as $attr) {
                $attrName = strtolower($attr->nodeName);
                $attrVal = trim((string) $attr->nodeValue);

                if (str_starts_with($attrName, 'on')) {
                    $attrsToRemove[] = $attr->nodeName;
                    continue;
                }

                if ($tag === 'a' && $attrName === 'href') {
                    if (!preg_match('/^(https?:|mailto:|tel:|\/(?!\/))/i', $attrVal)) {
                        $attrsToRemove[] = $attr->nodeName;
                    }
                    continue;
                }

                if ($tag === 'img' && $attrName === 'src') {
                    $safeImage = preg_match('/^(https?:|\/(?!\/))/i', $attrVal)
                        || preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,[A-Za-z0-9+\/=]+$/i', $attrVal);
                    if (!$safeImage) {
                        $attrsToRemove[] = $attr->nodeName;
                    }
                    continue;
                }

                if ($tag === 'img' && in_array($attrName, ['alt', 'title', 'width', 'height'], true)) {
                    if (in_array($attrName, ['width', 'height'], true) && !preg_match('/^\d{1,4}(px|%)?$/i', $attrVal)) {
                        $attrsToRemove[] = $attr->nodeName;
                    }
                    continue;
                }

                if (in_array($tag, ['td', 'th'], true) && in_array($attrName, ['colspan', 'rowspan'], true)) {
                    if (!preg_match('/^\d{1,3}$/', $attrVal)) {
                        $attrsToRemove[] = $attr->nodeName;
                    }
                    continue;
                }

                if (in_array($attrName, ['data-type', 'data-checked', 'colwidth'], true)) {
                    if (!preg_match('/^[A-Za-z0-9_,\-\s]+$/', $attrVal)) {
                        $attrsToRemove[] = $attr->nodeName;
                    }
                    continue;
                }

                if ($tag === 'input' && in_array($attrName, ['type', 'checked', 'disabled'], true)) {
                    continue;
                }

                if ($tag === 'ul' && $attrName === 'data-type' && $attrVal === 'taskList') {
                    continue;
                }

                if ($attrName === 'style') {
                    $sanitizedStyle = self::sanitizeStyle($attrVal);
                    if ($sanitizedStyle === null) {
                        $attrsToRemove[] = $attr->nodeName;
                    }
                    continue;
                }

                if ($attrName === 'data-color' && preg_match('/^(#[0-9a-f]{3,8}|rgb\([^;)]+\))$/i', $attrVal)) {
                    continue;
                }

                $attrsToRemove[] = $attr->nodeName;
            }

            foreach ($attrsToRemove as $attrName) {
                $child->removeAttribute($attrName);
            }

            if ($sanitizedStyle !== null) {
                $child->setAttribute('style', $sanitizedStyle);
            }

            if ($tag === 'img' && !$child->hasAttribute('src')) {
                $node->removeChild($child);
                continue;
            }

            if ($tag === 'a' && $child->hasAttribute('href')) {
                $child->setAttribute('rel', 'noopener noreferrer');
                $child->setAttribute('target', '_blank');
            }

            self::sanitizeNode($child);
        }
    }

    private static function sanitizeStyle(string $style): ?string
    {
        $allowedProps = [];
        foreach (explode(';', $style) as $rule) {
            $parts = explode(':', $rule, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $prop = strtolower(trim($parts[0]));
            $val = trim($parts[1]);

            if ($val === '' || preg_match('/(url|expression|javascript)/i', $val)) {
                continue;
            }

            if (in_array($prop, ['color', 'background-color'], true)
                && preg_match('/^(#[0-9a-f]{3,8}|rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+(?:\s*,\s*[\d.]+)?\s*\)|hsla?\([^;)]+\)|[a-z]{3,20})$/i', $val)) {
                $allowedProps[] = "{$prop}: {$val}";
            } elseif ($prop === 'text-align' && in_array(strtolower($val), ['left', 'right', 'center', 'justify'], true)) {
                $allowedProps[] = 'text-align: ' . strtolower($val);
            } elseif (in_array($prop, ['width', 'min-width'], true) && preg_match('/^\d{1,4}(px|%|rem|em)$/', $val)) {
                $allowedProps[] = "{$prop}: {$val}";
            }
        }

        return empty($allowedProps) ? null : implode('; ', $allowedProps);
    }
}
