<?php

declare(strict_types=1);

namespace App\Domains\CMS\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Server-side allow-list sanitizer for CMS rich-text HTML.
 *
 * Rich-text stored by admin and partners (blog bodies, static pages, tour descriptions)
 * is untrusted input that is later rendered in public responses. This service removes dangerous
 * elements together with their payloads, refuses inline event handlers and unsafe URI schemes,
 * and keeps only the editorial markup the official editor supports while strictly
 * validating inline styles against a safe CSS allowlist.
 */
final class HtmlSanitizerService
{
    /**
     * Editorial elements retained in sanitized output, with their allowed
     * attributes.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ELEMENTS = [
        'p' => ['style', 'class'],
        'br' => [],
        'h1' => ['style', 'class'],
        'h2' => ['style', 'class'],
        'h3' => ['style', 'class'],
        'h4' => ['style', 'class'],
        'h5' => ['style', 'class'],
        'h6' => ['style', 'class'],
        'strong' => ['style', 'class'],
        'b' => ['style', 'class'],
        'em' => ['style', 'class'],
        'i' => ['style', 'class'],
        'u' => ['style', 'class'],
        's' => ['style', 'class'],
        'ul' => ['style', 'class'],
        'ol' => ['style', 'class'],
        'li' => ['style', 'class'],
        'blockquote' => ['style', 'class'],
        'pre' => ['style', 'class'],
        'code' => ['style', 'class'],
        'a' => ['href', 'title', 'target', 'rel', 'style', 'class'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading', 'style', 'class'],
        'table' => ['border', 'cellpadding', 'cellspacing', 'style', 'class'],
        'thead' => ['style', 'class'],
        'tbody' => ['style', 'class'],
        'tr' => ['style', 'class'],
        'th' => ['colspan', 'rowspan', 'scope', 'style', 'class'],
        'td' => ['colspan', 'rowspan', 'style', 'class'],
        'figure' => ['style', 'class'],
        'figcaption' => ['style', 'class'],
        'hr' => ['style', 'class'],
        'span' => ['style', 'class'],
        'div' => ['style', 'class'],
    ];

    /**
     * Dangerous elements removed together with their children. Using
     * `dropElement()` (rather than `blockElement()`) guarantees the inner
     * script/style payload never survives as text.
     *
     * @var list<string>
     */
    private const DANGEROUS_ELEMENTS = [
        'script',
        'iframe',
        'object',
        'embed',
        'svg',
        'math',
        'form',
        'input',
        'style',
        'link',
        'meta',
        'base',
    ];

    /**
     * Strictly allowlisted CSS properties for inline style attributes.
     *
     * @var list<string>
     */
    private const ALLOWED_CSS_PROPERTIES = [
        'color',
        'background-color',
        'text-align',
        'font-size',
        'font-family',
        'font-weight',
        'font-style',
        'text-decoration',
        'line-height',
        'width',
        'height',
        'max-width',
        'min-width',
        'margin',
        'margin-left',
        'margin-right',
        'margin-top',
        'margin-bottom',
        'padding',
        'padding-left',
        'padding-right',
        'padding-top',
        'padding-bottom',
        'border',
        'border-color',
        'border-style',
        'border-width',
        'border-collapse',
        'vertical-align',
        'float',
        'list-style-type',
    ];

    private readonly HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = new HtmlSanitizerConfig;

        foreach (self::ALLOWED_ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        $config = $config
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeLinks(false)
            ->allowRelativeMedias(true)
            ->withMaxInputLength(1_000_000);

        foreach (self::DANGEROUS_ELEMENTS as $element) {
            $config = $config->dropElement($element);
        }

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $sanitized = $this->sanitizer->sanitize($html);

        return $this->sanitizeInlineStyles($sanitized);
    }

    /**
     * Sanitize every locale entry in a JSONB-style localized map.
     *
     * Non-string values (e.g. null) are preserved as-is so callers can keep
     * their existing "locale omitted" semantics.
     *
     * @param  array<string, mixed>|null  $map
     * @return array<string, mixed>
     */
    public function sanitizeLocalizedMap(?array $map): array
    {
        if ($map === null) {
            return [];
        }

        $sanitized = [];

        foreach ($map as $locale => $value) {
            $sanitized[$locale] = is_string($value) ? $this->sanitize($value) : $value;
        }

        return $sanitized;
    }

    /**
     * Strictly filter inline CSS declarations in style attributes against safe properties and values.
     */
    private function sanitizeInlineStyles(string $html): string
    {
        return (string) preg_replace_callback('/\s*style\s*=\s*["\']([^"\']*)["\']/i', function (array $matches): string {
            $cleanStyle = $this->cleanCssDeclarations((string) $matches[1]);

            return $cleanStyle !== '' ? ' style="' . htmlspecialchars($cleanStyle, ENT_QUOTES, 'UTF-8') . '"' : '';
        }, $html);
    }

    /**
     * Parse and validate CSS declarations, discarding any dangerous or non-allowlisted rules.
     */
    private function cleanCssDeclarations(string $style): string
    {
        $declarations = explode(';', $style);
        $safe = [];

        foreach ($declarations as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '') {
                continue;
            }

            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if (! in_array($property, self::ALLOWED_CSS_PROPERTIES, true)) {
                continue;
            }

            // Reject URLs, script expressions, event bindings, behaviors, and HTML injection
            if (preg_match('/[<>"\'`]|url\s*\(|expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding|@import/i', $value)) {
                continue;
            }

            // Allow only safe characters: letters, digits, spaces, hyphens, hashes, percent, commas, periods, parentheses (for colors)
            if (! preg_match('/^[\w\s\-\#\%\,\.\(\)]+$/', $value)) {
                continue;
            }

            $safe[] = $property . ': ' . $value;
        }

        return implode('; ', $safe);
    }
}
