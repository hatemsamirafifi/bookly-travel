<?php

namespace App\Domains\Translation\Contracts;

/**
 * Strategy for translating one content field (Admin 2.0 Phase H, T-H02).
 *
 * Implementations return the translated text plus the provider/model used so
 * the pipeline can record provenance on TranslationFieldState rows.
 */
interface ContentTranslatorContract
{
    /**
     * Translate one English source field into a target locale.
     *
     * @param  bool  $isHtml  Preserve HTML structure (blog/page bodies).
     * @return array{translated_text: string, provider: string, model: string}
     */
    public function translateField(string $sourceText, string $targetLocale, string $fieldName, bool $isHtml = false): array;
}
