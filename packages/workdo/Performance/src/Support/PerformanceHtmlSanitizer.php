<?php

namespace Workdo\Performance\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Strict allowlist HTML sanitizer for performance appraisal rich-text fields.
 */
class PerformanceHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'span', 'div', 'a',
    ];

    private const DROP_WITH_CONTENT_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'textarea', 'button',
    ];

    /**
     * Sanitize untrusted HTML while preserving safe rich-text formatting.
     */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $trimmed = trim($html);
        if ($trimmed === '') {
            return null;
        }

        // Fast-path plain text without tags
        if (!str_contains($trimmed, '<')) {
            return e($trimmed);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $wrapped = '<?xml encoding="utf-8" ?><div id="perf-sanitize-root">' . $trimmed . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('perf-sanitize-root');
        if (!$root) {
            return e(strip_tags($trimmed));
        }

        self::sanitizeNode($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        $output = trim($output);
        return $output === '' ? null : $output;
    }

    /**
     * Recursively strip disallowed tags, event handlers, and unsafe URL schemes.
     */
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
                // Unwrap unknown elements while sanitizing their text/safe children
                self::sanitizeNode($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            // Strip all attributes except safe href on <a>
            $attrsToRemove = [];
            foreach ($child->attributes as $attr) {
                $attrName = strtolower($attr->nodeName);
                if ($tag === 'a' && $attrName === 'href') {
                    $href = trim($attr->nodeValue);
                    if (!preg_match('/^(https?:|mailto:)/i', $href)) {
                        $attrsToRemove[] = $attr->nodeName;
                    }
                } else {
                    $attrsToRemove[] = $attr->nodeName;
                }
            }

            foreach ($attrsToRemove as $attrName) {
                $child->removeAttribute($attrName);
            }

            if ($tag === 'a' && $child->hasAttribute('href')) {
                $child->setAttribute('rel', 'noopener noreferrer');
                $child->setAttribute('target', '_blank');
            }

            self::sanitizeNode($child);
        }
    }
}
