<?php

declare(strict_types=1);

namespace App\Domains\CMS\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Server-side allow-list sanitizer for CMS rich-text HTML.
 *
 * Rich-text stored by the admin (blog bodies, static pages) is untrusted input
 * that is later rendered in public responses. This service removes dangerous
 * elements together with their payloads, refuses inline event handlers and
 * unsafe URI schemes, and keeps only the editorial markup the editor supports.
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
        'p' => [],
        'br' => [],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'pre' => [],
        'code' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => [],
        'td' => [],
        'figure' => [],
        'figcaption' => [],
        'hr' => [],
        'span' => [],
        'div' => [],
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

        return $this->sanitizer->sanitize($html);
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
}
