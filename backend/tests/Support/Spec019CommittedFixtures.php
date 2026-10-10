<?php

namespace Tests\Support;

use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\TourTranslationState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * US3 T041: named committed-connection fixtures for real after-commit tests.
 *
 * A separate connection permits actual commits while RefreshDatabase retains
 * its wrapping transaction. Nested-callback tests install the production
 * transaction manager. Cleanup uses tracked IDs or their owned partner scope.
 */
class Spec019CommittedFixtures
{
    public const CONNECTION = 'spec019_committed';

    public const EXPECTED_DATABASE = 'bookly_test';

    public static function boot(): void
    {
        config()->set(
            'database.connections.' . self::CONNECTION,
            config('database.connections.pgsql')
        );
        DB::purge(self::CONNECTION);
    }

    public static function assertPrerequisites(): void
    {
        $identity = DB::connection(self::CONNECTION)->selectOne('select current_database() as db, current_user as usr');
        expect($identity->db)->toBe(self::EXPECTED_DATABASE);
        expect($identity->usr)->toBe(self::EXPECTED_DATABASE);
        expect(DB::connection(self::CONNECTION)->transactionLevel())->toBe(0);
    }

    /**
     * Create an isolated owner fixture (user + partner + category + token) on
     * the committed connection. The caller must already route the default
     * connection at spec019_committed. Returns tracked table/id pairs plus
     * the bearer token and category slug.
     *
     * @return array{token: string, categorySlug: string, tables: array<string, array<int, int|string>>}
     */
    public static function createOwner(string $suffix, array &$fixture): array
    {
        $category = Category::create([
            'slug' => 'spec019-commit-' . $suffix . '-' . bin2hex(random_bytes(5)),
            'name' => 'Spec019 Commit ' . $suffix,
        ]);
        $fixture['tables'][$category->getTable()][] = $category->getKey();
        $user = User::factory()->partner()->create();
        $fixture['tables'][$user->getTable()][] = $user->getKey();
        $partner = Partner::create([
            'user_id' => $user->id,
            'role' => 'partner',
            'onboarding_status' => 'complete',
            'is_active' => true,
        ]);
        $fixture['tables'][$partner->getTable()][] = $partner->getKey();
        $tokenResult = $user->createToken('spec019', ['partner']);
        $fixture['tables'][$tokenResult->accessToken->getTable()][] = $tokenResult->accessToken->getKey();

        return [
            'token' => $tokenResult->plainTextToken,
            'categorySlug' => $category->slug,
            'tables' => $fixture['tables'],
        ];
    }

    /**
     * Track a tour and its translation rows for scoped cleanup.
     *
     * @param  array{token: string, categorySlug: string, tables: array<string, array<int, int|string>>}  $fixture
     */
    public static function trackTour(array &$fixture, int $tourId): void
    {
        $tour = Tour::findOrFail($tourId);
        $fixture['tables'][$tour->getTable()][] = $tour->getKey();
        foreach ($tour->translations()->pluck('id')->all() as $id) {
            $fixture['tables'][(new TourTranslation)->getTable()][] = $id;
        }
        foreach ($tour->translationStates()->pluck('id')->all() as $id) {
            $fixture['tables'][(new TourTranslationState)->getTable()][] = $id;
        }
    }

    /**
     * Delete ONLY the tracked fixture ids on the committed connection using
     * query-builder deletes (no model events, no extra dispatches).
     *
     * @param  array{tables?: array<string, array<int, int|string>>}  $fixture
     */
    public static function deleteOnly(array $fixture): void
    {
        foreach (array_reverse($fixture['tables'] ?? [], true) as $table => $ids) {
            $ids = array_values(array_unique($ids));
            if ($ids !== []) {
                DB::connection(self::CONNECTION)->table($table)->whereIn('id', $ids)->delete();
            }
        }
    }

    /**
     * Delete a suite owner's committed tour scope before the tracked rows.
     *
     * Removes every tour owned by the tracked partners plus any explicitly
     * tracked tour ids through query-builder deletes (no model events, no
     * extra dispatches). Tour child rows (translations, states, media,
     * pricing, availability, drafts, wishlists) cascade at the DB level.
     * Remaining tracked rows (category/user/partner/token) are removed via
     * deleteOnly() afterwards, so callers keep one cleanup call.
     *
     * @param  array{tables?: array<string, array<int, int|string>>}  $fixture
     */
    public static function deleteOwnerScope(array $fixture): void
    {
        $connection = DB::connection(self::CONNECTION);
        $tables = $fixture['tables'] ?? [];

        $partnerIds = array_values(array_unique($tables[(new Partner)->getTable()] ?? []));
        $tourTable = (new Tour)->getTable();
        $tourIds = array_values(array_unique($tables[$tourTable] ?? []));
        if ($partnerIds !== []) {
            $scoped = $connection->table($tourTable)->whereIn('partner_id', $partnerIds)->pluck('id')->all();
            $tourIds = array_values(array_unique(array_merge($tourIds, $scoped)));
        }

        if ($tourIds !== []) {
            $connection->table($tourTable)->whereIn('id', $tourIds)->delete();
        }

        self::deleteOnly($fixture);
    }
}
