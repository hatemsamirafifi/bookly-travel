<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->jsonb('guide_languages')->nullable();
        });

        Schema::table('tour_translations', function (Blueprint $table) {
            $table->jsonb('itinerary')->nullable();
        });

        $this->backfillLegacyItineraries();
    }

    public function backfillLegacyItineraries(): void
    {
        DB::table('tours')->whereNotNull('itinerary')->select(['id', 'itinerary'])
            ->orderBy('id')->chunkById(100, function ($tours): void {
                foreach ($tours as $tour) {
                    $days = $this->normalizeLegacyItinerary($tour->itinerary);
                    if ($days === []) {
                        continue;
                    }

                    DB::table('tour_translations')
                        ->where('tour_id', $tour->id)
                        ->where('locale', 'en')
                        ->whereNull('itinerary')
                        ->update(['itinerary' => json_encode($days, JSON_THROW_ON_ERROR)]);
                }
            });
    }

    private function normalizeLegacyItinerary(mixed $raw): array
    {
        $items = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($items) || count($items) > 30) {
            return [];
        }

        $days = [];
        foreach (array_values($items) as $index => $item) {
            if (is_string($item)) {
                $item = ['title' => $item];
            }
            if (! is_array($item) || ! is_string($item['title'] ?? null)) {
                return [];
            }

            $title = trim($item['title']);
            $day = $item['day'] ?? $index + 1;
            $stops = $item['stops'] ?? [];
            $description = $item['description'] ?? null;
            if ($title === '' || mb_strlen($title) > 160 || ! is_int($day) || $day < 1 || $day > 30
                || ($description !== null && (! is_string($description) || mb_strlen($description) > 2000))
                || ! is_array($stops) || count($stops) > 20) {
                return [];
            }

            $normalizedStops = [];
            foreach ($stops as $stop) {
                if (! is_array($stop)) {
                    return [];
                }
                $stopTitle = is_string($stop['title'] ?? null) ? trim($stop['title']) : '';
                $stopDescription = $stop['description'] ?? null;
                $minutes = $stop['duration_minutes'] ?? null;
                if ($stopTitle === '' || mb_strlen($stopTitle) > 160
                    || ($stopDescription !== null && (! is_string($stopDescription) || mb_strlen($stopDescription) > 2000))
                    || ($minutes !== null && (! is_int($minutes) || $minutes < 1 || $minutes > 1440))) {
                    return [];
                }

                $normalizedStops[] = [
                    'title' => $stopTitle,
                    'description' => $stopDescription,
                    'duration_minutes' => $minutes,
                ];
            }

            $days[] = [
                'day' => $day,
                'title' => $title,
                'description' => $description,
                'stops' => $normalizedStops,
            ];
        }

        return $days;
    }

    public function down(): void
    {
        Schema::table('tour_translations', function (Blueprint $table) {
            $table->dropColumn('itinerary');
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('guide_languages');
        });
    }
};
