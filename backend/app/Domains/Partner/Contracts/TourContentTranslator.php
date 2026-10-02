<?php

namespace App\Domains\Partner\Contracts;

interface TourContentTranslator
{
    /**
     * @param  array<string, string>  $strings  Field path => English text.
     * @return array<string, string> Field path => translated text.
     */
    public function translate(array $strings, string $locale): array;
}
