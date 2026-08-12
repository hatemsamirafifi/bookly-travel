<?php

namespace App\Domains\Reviews\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProfanityFilterService
{
    private array $keywords = [];

    /**
     * Load profanity keywords from the configured default (tracked) path,
     * optionally replaced by a per-environment override file.
     *
     * Paths default to config('profanity.*') but may be injected for testing.
     * Override semantics: a present override file FULLY REPLACES the default.
     */
    public function __construct(?string $defaultPath = null, ?string $overridePath = null)
    {
        $defaultPath ??= config('profanity.default_path');
        $overridePath ??= config('profanity.override_path');

        $path = null;
        if ($overridePath !== null && File::exists($overridePath)) {
            $path = $overridePath;
        } elseif ($defaultPath !== null && File::exists($defaultPath)) {
            $path = $defaultPath;
        }

        if ($path !== null) {
            $this->keywords = json_decode(File::get($path), true) ?? [];
        }

        if (empty($this->keywords)) {
            // FR-014: a silent no-op filter is worse than none — make the
            // misconfiguration visible in logs instead of swallowing it.
            Log::warning('ProfanityFilterService: no profanity keywords loaded — filter is a no-op.', [
                'default_path' => $defaultPath,
                'override_path' => $overridePath,
            ]);
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
