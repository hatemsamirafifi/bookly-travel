<?php

namespace App\Domains\Partner\Contracts;

/**
 * Provider boundary for tours whose authored source is not English.
 *
 * `TourContentTranslator::translate()` keeps its English-source signature so
 * existing callers and test doubles stay valid. A translator that can also
 * translate from another authored source locale implements this interface;
 * generation from a non-English source requires it, because guessing the
 * source language would silently mistranslate the author's content.
 */
interface SourceAwareTourContentTranslator
{
    /**
     * @param  array<string, string>  $strings  Field path => source text.
     * @return array<string, string> Field path => translated text.
     */
    public function translateFrom(array $strings, string $sourceLocale, string $targetLocale): array;
}
