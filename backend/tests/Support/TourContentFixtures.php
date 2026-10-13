<?php

namespace Tests\Support;

use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourTranslationService;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic Spec 019 fixtures and coordination primitives (T009).
 *
 * All helpers target the disposable test database only (bookly_test on
 * bookly-test-postgres via phpunit.pgsql.xml). No real credentials or
 * network calls: generation fakes live in Spec019FakeTranslator and tests
 * run with a blank GEMINI_API_KEY.
 */
class TourContentFixtures
{
    /**
     * Deterministic English source attributes for tour_translations.
     */
    public static function englishAttributes(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Morning walking tour',
            'description' => 'Current English description.',
            'highlights' => ['Old-town route'],
            'inclusions' => ['Live guide'],
            'exclusions' => [],
            'meeting_point' => 'Main square',
            'cancellation_policy' => 'Free cancellation.',
            'itinerary' => [
                ['day' => 1, 'title' => 'Old town', 'description' => null, 'stops' => []],
            ],
            'important_information' => ['Comfortable shoes recommended'],
        ], $overrides);
    }

    /**
     * Seed a deterministic derivative/state combination for one locale.
     *
     * Kinds: current (ready + matching translated_hash + stored derivative),
     * missing (no state row and no derivative), stale (ready status whose
     * translated_hash no longer matches the fresh source hash, old
     * derivative retained), failed (failed status with a sanitized error
     * code, old derivative retained).
     */
    public static function seedDerivative(Tour $tour, string $locale, string $kind): void
    {
        $service = app(TourTranslationService::class);
        $english = $tour->translations()->where('locale', 'en')->firstOrFail();
        $hash = $service->sourceHash($english);

        switch ($kind) {
            case 'current':
                $tour->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['title' => "Title {$locale}", 'description' => "Description {$locale}"]
                );
                $tour->translationStates()->updateOrCreate(
                    ['locale' => $locale],
                    ['source_hash' => $hash, 'translated_hash' => $hash, 'status' => 'ready', 'last_error_code' => null]
                );

                break;
            case 'missing':
                $tour->translations()->where('locale', $locale)->delete();
                $tour->translationStates()->where('locale', $locale)->delete();

                break;
            case 'stale':
                $tour->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['title' => "Old title {$locale}", 'description' => "Old description {$locale}"]
                );
                $tour->translationStates()->updateOrCreate(
                    ['locale' => $locale],
                    ['source_hash' => $hash, 'translated_hash' => str_repeat('0', 64), 'status' => 'ready', 'last_error_code' => null]
                );

                break;
            case 'failed':
                $tour->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['title' => "Old title {$locale}", 'description' => "Old description {$locale}"]
                );
                $tour->translationStates()->updateOrCreate(
                    ['locale' => $locale],
                    ['source_hash' => $hash, 'translated_hash' => null, 'status' => 'failed', 'last_error_code' => 'translation_provider_temporary']
                );

                break;
            default:
                throw new \InvalidArgumentException("Unknown derivative kind: {$kind}");
        }
    }

    /**
     * Build two partners whose record IDs are guaranteed distinct from
     * both user IDs and from each other, so IDOR tests prove the scoped
     * lookup uses partner-record IDs and never user IDs.
     *
     * Explicit high IDs break the lockstep both sequences would otherwise
     * follow in a fresh database; the partners sequence is repaired
     * afterwards so later inserts never collide.
     *
     * @return array{0: Partner, 1: Partner}
     */
    public static function partnerPairWithDistinctIds(): array
    {
        $make = static function (int $offset): Partner {
            $user = User::factory()->partner()->create();
            $partner = new Partner([
                'user_id' => $user->id,
                'role' => 'partner',
                'onboarding_status' => 'complete',
                'is_active' => true,
            ]);
            $partner->id = max(User::max('id') ?? 0, Partner::max('id') ?? 0) + 1000 + $offset;
            $partner->save();

            return $partner->fresh() ?? $partner;
        };

        $first = $make(1);
        $second = $make(2);

        DB::statement("SELECT setval('partners_id_seq', (SELECT GREATEST(max(id), 1) FROM partners))");

        $userIds = [$first->user_id, $second->user_id];
        foreach ([$first, $second] as $partner) {
            if (in_array($partner->id, $userIds, true)) {
                throw new \RuntimeException('Fixture guarantee violated: partner record ID collides with a user ID.');
            }
        }
        if ($first->id === $second->id) {
            throw new \RuntimeException('Fixture guarantee violated: partner record IDs collide.');
        }

        return [$first, $second];
    }

    /**
     * Independent second PDO connection to the same disposable test
     * database. RefreshDatabase transactions are per-connection, so
     * cross-connection tests must only rely on committed state or on
     * database-level primitives (row locks, advisory locks); row fixtures
     * written inside the test transaction stay invisible here by design.
     */
    public static function secondConnection(): Connection
    {
        $config = config('database.connections.pgsql');
        config()->set('database.connections.spec019_second', $config);
        DB::purge('spec019_second');

        return DB::connection('spec019_second');
    }
}
