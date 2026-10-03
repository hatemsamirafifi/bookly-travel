<?php

namespace Tests\Support;

use App\Domains\Partner\Contracts\TourContentTranslator;

/**
 * Deterministic fake provider (T009): appends a locale suffix to every
 * source string, preserving numbers, nulls and array structure. Records
 * arrivals so tests can assert exactly which locales were requested
 * without any network traffic. PSR-4 file placement keeps it loadable
 * independently of suite ordering.
 */
class Spec019FakeTranslator implements TourContentTranslator
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
        $this->arrivals[] = $locale;
        if ($this->onArrival !== null) {
            ($this->onArrival)($locale, $strings);
        }
        $translated = [];
        foreach ($strings as $path => $value) {
            $translated[$path] = $value . " ({$locale})";
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
