<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Support;

class HtmlSanitizer
{
    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (!class_exists(\DOMDocument::class)) {
            $clean = strip_tags($html, '<p><br><strong><em><u><ul><ol><li><a><blockquote><h1><h2><h3><h4><span><table><thead><tbody><tr><th><td>');
            $clean = preg_replace('/javascript\s*:/i', '', $clean) ?? $clean;
            return trim($clean);
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $wrapped = '<!DOCTYPE html><html><body><div id="mf-root">' . $html . '</div></body></html>';
        $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('mf-root');
        if (!$root instanceof \DOMElement) {
            return '';
        }

        self::sanitizeNode($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMElement) {
                if (!self::isAllowedTag($child->tagName)) {
                    if (self::shouldRemoveWithChildren($child->tagName)) {
                        $node->removeChild($child);
                        continue;
                    }

                    self::unwrapNode($child);
                    continue;
                }

                self::sanitizeAttributes($child);
                self::sanitizeNode($child);
                continue;
            }

            if ($child instanceof \DOMComment) {
                $node->removeChild($child);
            }
        }
    }

    private static function isAllowedTag(string $tagName): bool
    {
        return in_array(strtolower($tagName), [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u',
            'ul', 'ol', 'li', 'a', 'blockquote',
            'h1', 'h2', 'h3', 'h4',
            'span', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
        ], true);
    }

    private static function shouldRemoveWithChildren(string $tagName): bool
    {
        return in_array(strtolower($tagName), [
            'script', 'style', 'iframe', 'object', 'embed', 'form',
            'input', 'button', 'select', 'option', 'textarea', 'meta', 'link',
        ], true);
    }

    private static function unwrapNode(\DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (!$parent instanceof \DOMNode) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private static function sanitizeAttributes(\DOMElement $element): void
    {
        $allowedAttributes = match (strtolower($element->tagName)) {
            'a' => ['href', 'target', 'rel', 'style'],
            'table' => ['style'],
            'th', 'td' => ['colspan', 'rowspan', 'scope', 'style'],
            'p', 'h1', 'h2', 'h3', 'h4', 'span', 'blockquote', 'ul', 'ol', 'li', 'tr', 'thead', 'tbody' => ['style'],
            default => [],
        };

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if (str_starts_with($name, 'on') || !in_array($name, $allowedAttributes, true)) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            $value = trim($attribute->value);
            if ($name === 'href') {
                $safeHref = self::sanitizeHref($value);
                if ($safeHref === null) {
                    $element->removeAttribute('href');
                } else {
                    $element->setAttribute('href', $safeHref);
                }
                continue;
            }

            if ($name === 'target') {
                $element->setAttribute('target', $value === '_blank' ? '_blank' : '_self');
                continue;
            }

            if ($name === 'rel') {
                $element->setAttribute('rel', 'noopener noreferrer');
                continue;
            }

            if ($name === 'style') {
                $safeStyle = self::sanitizeStyle($value);
                if ($safeStyle === '') {
                    $element->removeAttribute('style');
                } else {
                    $element->setAttribute('style', $safeStyle);
                }
                continue;
            }

            if (in_array($name, ['colspan', 'rowspan'], true)) {
                $number = max(1, min(20, (int) $value));
                $element->setAttribute($name, (string) $number);
                continue;
            }

            if ($name === 'scope') {
                $scope = in_array($value, ['col', 'row', 'colgroup', 'rowgroup'], true) ? $value : 'col';
                $element->setAttribute('scope', $scope);
            }
        }
    }

    private static function sanitizeHref(string $href): ?string
    {
        if ($href === '') {
            return null;
        }

        if (preg_match('/^\s*(https?:|mailto:|tel:|#|\/)/i', $href) === 1) {
            return preg_replace('/\s+/u', '', $href) ?: null;
        }

        return null;
    }

    private static function sanitizeStyle(string $style): string
    {
        $allowedProperties = [
            'color',
            'background-color',
            'font-size',
            'font-family',
            'text-align',
            'font-weight',
            'font-style',
            'text-decoration',
            'width',
            'border',
            'border-color',
            'border-width',
            'border-style',
            'border-collapse',
            'padding',
            'vertical-align',
        ];

        $safeDeclarations = [];
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (!in_array($property, $allowedProperties, true)) {
                continue;
            }

            if (!self::isSafeStyleValue($property, $value)) {
                continue;
            }

            $safeDeclarations[] = $property . ': ' . $value;
        }

        return implode('; ', $safeDeclarations);
    }

    private static function isSafeStyleValue(string $property, string $value): bool
    {
        if ($value === '' || preg_match('/expression|url\s*\(|javascript\s*:/i', $value) === 1) {
            return false;
        }

        return match ($property) {
            'color', 'background-color', 'border-color' => preg_match('/^(#[0-9a-f]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\)|[a-z\s-]+)$/i', $value) === 1,
            'font-size', 'width', 'border-width', 'padding' => preg_match('/^\d+(\.\d+)?(px|em|rem|%|pt)?$/i', $value) === 1,
            'font-family' => preg_match('/^[a-z0-9,"\s-]+$/i', $value) === 1,
            'text-align' => in_array(strtolower($value), ['left', 'center', 'right', 'justify'], true),
            'font-weight' => preg_match('/^(normal|bold|bolder|lighter|[1-9]00)$/i', $value) === 1,
            'font-style' => in_array(strtolower($value), ['normal', 'italic', 'oblique'], true),
            'text-decoration' => preg_match('/^(none|underline|line-through|overline)(\s+(solid|double|dotted|dashed|wavy))?$/i', $value) === 1,
            'border-style' => preg_match('/^(none|solid|dashed|dotted|double)$/i', $value) === 1,
            'border-collapse' => in_array(strtolower($value), ['collapse', 'separate'], true),
            'vertical-align' => in_array(strtolower($value), ['top', 'middle', 'bottom'], true),
            'border' => preg_match('/^[a-z0-9#().,\s%-]+$/i', $value) === 1,
            default => false,
        };
    }
}
