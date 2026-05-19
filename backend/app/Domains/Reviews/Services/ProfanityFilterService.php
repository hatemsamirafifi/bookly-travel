<?php

namespace App\Domains\Reviews\Services;

use Illuminate\Support\Facades\File;

class ProfanityFilterService
{
    private array $keywords = [];

    public function __construct()
    {
        $path = storage_path('app/profanity_keywords.json');

        if (File::exists($path)) {
            $this->keywords = json_decode(File::get($path), true) ?? [];
        }
    }

    /**
     * Scan text for profanity matches.
     *
     * @return array Matched keywords (empty array if clean)
     */
    public function scan(?string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $matched = [];

        foreach ($this->keywords as $keyword) {
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/iu';
            if (preg_match($pattern, $text)) {
                $matched[] = $keyword;
            }
        }

        return array_unique($matched);
    }
}
