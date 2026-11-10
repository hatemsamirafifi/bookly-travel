<?php

namespace Tests\Support;

use App\Domains\Partner\Contracts\SourceAwareTourContentTranslator;
use App\Domains\Partner\Contracts\TourContentTranslator;

/**
 * Deterministic fake provider (T009): appends a locale suffix to every
 * source string, preserving numbers, nulls and array structure. Records
 * arrivals so tests can assert exactly which locales were requested
 * without any network traffic. PSR-4 file placement keeps it loadable
 * independently of suite ordering.
 *
 * The fake is source-aware (`translateFrom`) so generation jobs for tours
 * authored in a non-English source (es/it) run through the same boundary the
 * production source-aware Gemini translator implements, with the requested
 * target locale recorded identically.
 */
class Spec019FakeTranslator implements SourceAwareTourContentTranslator, TourContentTranslator
{
    /** @var array<int, string> */
    private array $arrivals = [];

    /** @var callable(string, array<string, string>): void|null */
    private $onArrival = null;

    /**
     * Register a hook invoked inside translate() after the arrival is
     * recorded but before output is produced. US3 timing tests suspend
     * the calling fiber here so the parent can observe the in-flight
     * arrival, then resume to collect the completed output.
     *
     * @param  callable(string, array<string, string>): void|null  $hook
     */
    public function onArrival(?callable $hook): void
    {
        $this->onArrival = $hook;
    }

    public function translate(array $strings, string $locale): array
    {
        return $this->translateFrom($strings, 'en', $locale);
    }

    public function translateFrom(array $strings, string $sourceLocale, string $targetLocale): array
    {
        $this->arrivals[] = $targetLocale;
        if ($this->onArrival !== null) {
            ($this->onArrival)($targetLocale, $strings);
        }
        $translated = [];
        foreach ($strings as $path => $value) {
            $translated[$path] = $value . " ({$targetLocale})";
        }

        return $translated;
    }

    /**
     * @return array<int, string>
     */
    public function arrivals(): array
    {
        return $this->arrivals;
    }

    public function arrivedCount(?string $locale = null): int
    {
        if ($locale === null) {
            return count($this->arrivals);
        }

        return count(array_filter($this->arrivals, static fn (string $entry): bool => $entry === $locale));
    }
}
