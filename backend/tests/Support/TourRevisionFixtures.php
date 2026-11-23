<?php

namespace Tests\Support;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourRevisionPayloadService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourContentRevision;
use App\Models\TourRevisionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Deterministic EGM-003 revision fixtures (execution manifest S1).
 *
 * Helpers target the disposable test database only and reuse the pre-existing
 * publication fixtures' shape (approved partner, canonical source row, source
 * locale in {en,es,it}) so every revision test reads like the EGM-002
 * regressions it extends. No provider or network boundaries are touched.
 */
class TourRevisionFixtures
{
    /** @param  array<string, mixed>  $overrides */
    public static function tour(
        ?Partner $partner = null,
        string $status = 'published',
        string $sourceLocale = 'en',
        array $overrides = [],
        array $sourceOverrides = [],
    ): Tour {
        $partner ??= self::partner();

        $tour = Tour::create(array_merge([
            'partner_id' => $partner->id,
            'category_id' => Category::firstOrCreate(['slug' => 'egm003-test'], ['name' => 'EGM003 Test'])->id,
            'slug' => 'egm003-' . uniqid(),
            'source_locale' => $sourceLocale,
            'location' => 'Rome, Italy',
            'location_slug' => Str::slug('Rome, Italy'),
            'duration_minutes' => 120,
            'duration_label' => '2 hours',
            'group_size_min' => 1,
            'group_size_max' => 10,
            'difficulty_level' => 'easy',
            'guide_languages' => ['en'],
            'price_amount' => 5000,
            'status' => $status,
            $status === 'published' ? 'published_at' : 'created_at' => now()->subDay(),
        ], $overrides));

        $tour->translations()->create(array_merge([
            'locale' => $sourceLocale,
            'title' => 'EGM003 authored source title',
            'description' => 'EGM003 authored source description in the governed source locale.',
            'meeting_point' => 'EGM003 authored meeting point',
        ], $sourceOverrides));

        return $tour->fresh() ?? $tour;
    }

    public static function partner(string $onboardingStatus = 'approved'): Partner
    {
        return Partner::create([
            'user_id' => User::factory()->partner()->create()->id,
            'role' => 'partner',
            'onboarding_status' => $onboardingStatus,
            'is_active' => $onboardingStatus === 'approved',
        ]);
    }

    public static function admin(string ...$flags): User
    {
        $admin = User::factory()->admin()->create();
        $admin->adminPermission()->create(['flags' => array_fill_keys(
            $flags === [] ? ['manage_tours'] : $flags,
            true,
        )]);

        return $admin->fresh('adminPermission') ?? $admin;
    }

    /**
     * The governed presentation patch that changes exactly one field.
     *
     * @return array<string, mixed>
     */
    public static function presentationPatch(array $overrides = []): array
    {
        return array_merge(['location' => 'Florence, Italy'], $overrides);
    }

    /**
     * The canonical source patch that changes exactly the title.
     *
     * @return array<string, mixed>
     */
    public static function sourcePatch(array $overrides = []): array
    {
        return array_merge(['title' => 'EGM003 proposed source title'], $overrides);
    }

    /**
     * A governed payload snapshot of the tour as it stands right now, without
     * touching the revision tables.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(Tour $tour): array
    {
        return app(TourRevisionPayloadService::class)->captureSnapshot($tour);
    }

    /**
     * Seed an approved baseline revision for a tour so a test can exercise the
     * candidate paths without the capture step.
     */
    public static function seedBaseline(Tour $tour): TourContentRevision
    {
        $payload = self::snapshot($tour);

        return TourContentRevision::create([
            'tour_id' => $tour->id,
            'revision_number' => 1,
            'kind' => TourContentRevision::KIND_LEGACY_BASELINE,
            'state' => TourContentRevision::STATE_APPROVED,
            'source_locale' => $tour->sourceLocale(),
            'payload' => $payload,
            'payload_hash' => app(TourRevisionPayloadService::class)->hashPayload($payload),
            'content_hash' => app(TourRevisionPayloadService::class)->canonicalHashOfPayload($payload),
        ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function refs(string ...$paths): array
    {
        return array_map(fn (string $path): array => ['path' => $path], $paths);
    }

    /**
     * A distinct second partner for cross-supplier 404 assertions, built on the
     * existing partner fixture factory.
     */
    public static function otherPartner(): Partner
    {
        return self::partner();
    }

    /** Committed, individually owned rows for real after-commit/index tests. */
    public static function withCommittedPublicTour(callable $scenario): void
    {
        $previous = DB::getDefaultConnection();
        config()->set('database.connections.egm003_public_owner', config('database.connections.pgsql'));
        DB::setDefaultConnection('egm003_public_owner');
        $partner = $otherPartner = $owner = $otherOwner = $category = $tour = $admin = null;
        try {
            $identity = DB::selectOne('select current_database() as db, current_user as usr');
            if ($identity->db !== 'bookly_test' || $identity->usr !== 'bookly_test') {
                throw new \RuntimeException('Refusing public ownership fixtures outside the disposable test database.');
            }
            $partner = self::partner();
            $otherPartner = self::partner();
            $owner = $partner->user;
            $otherOwner = $otherPartner->user;
            $admin = self::admin('manage_partners');
            $category = Category::create(['slug' => 'public-owner-' . uniqid(), 'name' => 'Public owner']);
            $tour = Tour::create([
                'partner_id' => $partner->id, 'category_id' => $category->id,
                'slug' => 'public-owner-' . uniqid(), 'source_locale' => 'en',
                'status' => 'published', 'published_at' => now()->subDay(),
                'price_amount' => 5000, 'location' => 'Rome, Italy', 'location_slug' => 'rome',
                'duration_minutes' => 120, 'duration_label' => '2 hours',
                'group_size_min' => 1, 'group_size_max' => 10,
            ]);
            $tour->translations()->create([
                'locale' => 'en', 'title' => 'Committed owner source', 'description' => 'Committed source description.',
            ]);
            AvailabilityRule::create([
                'tour_id' => $tour->id, 'rule_type' => 'recurring',
                'days_of_week' => [0, 1, 2, 3, 4, 5, 6], 'capacity' => 10,
            ]);
            $scenario(compact('partner', 'otherPartner', 'admin', 'category', 'tour'));
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($tour !== null) {
                DB::table('governance_audit_logs')->where('target_type', 'tour')->where('target_id', $tour->id)->delete();
                $tour->delete();
            }
            foreach ([$partner, $otherPartner] as $ownedPartner) {
                if ($ownedPartner !== null) {
                    DB::table('governance_audit_logs')->where('target_type', 'partner')->where('target_id', $ownedPartner->id)->delete();
                    $ownedPartner->delete();
                }
            }
            $owner?->delete();
            $otherOwner?->delete();
            $admin?->adminPermission()->delete();
            $admin?->delete();
            $category?->delete();
            DB::setDefaultConnection($previous);
            DB::purge('egm003_public_owner');
        }
    }

    /** Flush every governance row for a tour (used for idempotence assertions). */
    public static function truncateGovernance(): void
    {
        foreach ([TourRevisionRequest::class, TourContentRevision::class] as $class) {
            $class::query()->delete();
        }
    }

    /**
     * Reset the governance audit table for a fresh audit-count assertion.
     */
    public static function clearAuditLog(): void
    {
        app(GovernanceAuditService::class);
        DB::table('governance_audit_logs')->delete();
    }
}
