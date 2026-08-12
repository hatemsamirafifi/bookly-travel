<?php

use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Search\Actions\SearchToursAction;
use App\Domains\Search\Support\DurationBucket;
use App\Domains\Search\Support\SearchableAttributes;
use App\Models\Category;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Exposes the protected filter builder so we can assert the generated
 * Meilisearch filter string deterministically, without a live search engine.
 */
class TestableSearchToursAction extends SearchToursAction
{
    public function exposeBuildFilterString(array $params): string
    {
        return $this->buildFilterString($params);
    }
}

it('includes a price_amount <= 0 bound for price_max=0 (F7 free-tour filter)', function () {
    $action = new TestableSearchToursAction;

    $filter = $action->exposeBuildFilterString(['price_max' => '0']);

    expect($filter)->toContain('price_amount <= 0');
});

it('omits price bounds when none are provided (F7)', function () {
    $action = new TestableSearchToursAction;

    $filter = $action->exposeBuildFilterString([]);

    expect($filter)->not->toContain('price_amount');
});

it('includes both bounds when price_min and price_max are provided (F7)', function () {
    $action = new TestableSearchToursAction;

    $filter = $action->exposeBuildFilterString(['price_min' => '1000', 'price_max' => '5000']);

    expect($filter)->toContain('price_amount >= 1000')
        ->and($filter)->toContain('price_amount <= 5000');
});

it('maps each locale to its own searchable attributes + shared fields (F6)', function () {
    expect(SearchableAttributes::forLocale('es'))
        ->toContain('title_es', 'description_es', 'highlights_es', 'location', 'category_name')
        ->and(SearchableAttributes::forLocale('it'))
        ->toContain('title_it', 'description_it', 'highlights_it')
        ->and(SearchableAttributes::forLocale('en'))
        ->toContain('title_en', 'description_en', 'highlights_en')
        ->and(SearchableAttributes::forLocale('es'))->not->toContain('title_en');
});

it('uses the shared DurationBucket thresholds in the filter expression', function () {
    expect(DurationBucket::HalfDay->filterExpression())->toContain('duration_minutes <= 240')
        ->and(DurationBucket::FullDay->filterExpression())->toContain('duration_minutes >= 241')
        ->and(DurationBucket::FullDay->filterExpression())->toContain('duration_minutes <= 480')
        ->and(DurationBucket::MultiDay->filterExpression())->toContain('duration_minutes > 480');
});

it('scopes facet counts to bookable tours only (F5)', function () {
    $category = Category::create([
        'name' => 'Adventure', 'slug' => 'adventure',
        'is_active' => true, 'display_order' => 1,
    ]);
    $partner = makePartner()->id;

    // Bookable: published, priced, with an upcoming availability rule.
    $bookable = Tour::withoutEvents(fn () => Tour::create([
        'category_id' => $category->id, 'partner_id' => $partner,
        'price_amount' => 5000, 'slug' => 'bookable',
        'location' => 'Rome, Italy', 'location_slug' => 'rome',
        'duration_minutes' => 240, 'duration_label' => '4 hours',
        'group_size_min' => 1, 'group_size_max' => 10, 'status' => 'published',
    ]));
    AvailabilityRule::create([
        'tour_id' => $bookable->id, 'rule_type' => 'recurring',
        'days_of_week' => [0, 1, 2, 3, 4, 5, 6], 'capacity' => 10,
    ]);

    // Published but no pricing.
    Tour::withoutEvents(fn () => Tour::create([
        'category_id' => $category->id, 'partner_id' => $partner,
        'price_amount' => 0, 'slug' => 'no-pricing',
        'location' => 'Rome, Italy', 'location_slug' => 'rome',
        'duration_minutes' => 240, 'duration_label' => '4 hours',
        'group_size_min' => 1, 'group_size_max' => 10, 'status' => 'published',
    ]));

    // Published and priced but no availability rules.
    Tour::withoutEvents(fn () => Tour::create([
        'category_id' => $category->id, 'partner_id' => $partner,
        'price_amount' => 3000, 'slug' => 'no-availability',
        'location' => 'Milan, Italy', 'location_slug' => 'milan',
        'duration_minutes' => 240, 'duration_label' => '4 hours',
        'group_size_min' => 1, 'group_size_max' => 10, 'status' => 'published',
    ]));

    // Only the one bookable tour is counted.
    expect(Tour::bookable()->count())->toBe(1)
        ->and(Category::popularWithCounts()->first()->tours_count)->toBe(1);
});
