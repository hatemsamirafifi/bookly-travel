<?php

namespace App\Domains\Translation\Services;

use App\Domains\CMS\Services\HtmlSanitizerService;

/**
 * Output gate for field translations (Admin 2.0 Phase H, T-H02).
 *
 * A translated field only ever reaches content storage through this check:
 * whitespace-only output and echo-back from an echo-prone model are rejected
 * as failures, SEO-length overflow and non-empty sanitized HTML are flagged
 * for review rather than auto-rejected.
 */
class TranslationQualityValidator
{
    public function __construct(private readonly HtmlSanitizerService $htmlSanitizer) {}

    /**
     * Validate a translation against its English source.
     *
     * @return array{valid: bool, status: string, error_code: ?string, error_message: ?string, sanitized_text: string}
     */
    public function validate(string $sourceText, string $translatedText, string $targetLocale, string $fieldName, bool $isHtml = false): array
    {
        $result = [
            'valid' => true,
            'status' => 'completed',
            'error_code' => null,
            'error_message' => null,
            'sanitized_text' => '',
        ];

        $text = trim((string) $translatedText);

        if ($text === '') {
            return $this->reject($result, 'EMPTY_TRANSLATION', 'Translation returned empty output');
        }

        $sourcePlain = trim(strip_tags($sourceText));

        if (mb_strlen($sourcePlain) > 25 && $text === $sourcePlain) {
            return $this->reject($result, 'UNCHANGED_SOURCE_RETURNED', 'Translation echoed the source text back');
        }

        if ($isHtml) {
            $sanitized = $this->htmlSanitizer->sanitize($text);

            if (trim(strip_tags($sanitized)) === '') {
                return $this->reject($result, 'CORRUPTED_HTML', 'Sanitized HTML output contained no surviving content');
            }

            $result['sanitized_text'] = $sanitized;

            return $result;
        }

        $limits = [
            'meta_title' => 160,
            'meta_description' => 320,
        ];

        if (isset($limits[$fieldName]) && mb_strlen($text) > $limits[$fieldName]) {
            $result['valid'] = true;
            $result['status'] = 'needs_review';
            $result['error_code'] = 'OVER_LENGTH_SEO_FIELD';
            $result['error_message'] = "Translated {$fieldName} exceeds {$limits[$fieldName]} characters";
            $result['sanitized_text'] = strip_tags($text);

            return $result;
        }

        $result['sanitized_text'] = strip_tags($text);

        return $result;
    }

    /**
     * @param  array{valid: bool, status: string, error_code: ?string, error_message: ?string, sanitized_text: string}  $result
     * @return array{valid: bool, status: string, error_code: ?string, error_message: ?string, sanitized_text: string}
     */
    private function reject(array $result, string $errorCode, string $message): array
    {
        $result['valid'] = false;
        $result['status'] = 'failed';
        $result['error_code'] = $errorCode;
        $result['error_message'] = $message;

        return $result;
    }
}
