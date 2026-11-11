<?php

use App\Domains\Admin\Actions\ApproveTourAction;
use App\Domains\Admin\Actions\UnpublishTourAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Services\TourService;
use App\Filament\Resources\TourResource\Pages\EditTour;
use App\Filament\Resources\TourResource\Pages\ListTours;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function publicationInvariantTour(string $locale = 'en', string $status = 'pending_review'): Tour
{
    $partner = Partner::create([
        'user_id' => User::factory()->partner()->create()->id,
        'role' => 'partner', 'onboarding_status' => 'approved', 'is_active' => true,
    ]);
    $tour = Tour::create([
        'partner_id' => $partner->id,
        'category_id' => Category::firstOrCreate(['slug' => 'publication-test'], ['name' => 'Publication test'])->id,
        'slug' => 'publication-' . uniqid(), 'source_locale' => $locale,
        'location' => 'Rome', 'price_amount' => 5000, 'status' => $status,
        'duration_minutes' => 120, 'duration_label' => '2 hours',
    ]);
    $tour->translations()->create([
        'locale' => $locale, 'title' => 'Canonical title', 'description' => 'Canonical description.',
    ]);

    return $tour;
}

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->admin()->create();
    $this->admin->adminPermission()->create(['flags' => ['manage_tours' => true]]);
    $this->actingAs($this->admin);
});

it('publishes each canonical locale regardless of derivative readiness', function (string $locale, ?string $derivativeStatus) {
    $tour = publicationInvariantTour($locale);
    if ($derivativeStatus !== null) {
        foreach ($tour->derivativeLocales() as $derivative) {
            $tour->translationStates()->create([
                'locale' => $derivative, 'status' => $derivativeStatus,
                'source_hash' => str_repeat('a', 64),
            ]);
        }
    }
    $this->travelTo(now()->startOfSecond());

    $published = app(ApproveTourAction::class)->execute($this->admin, $tour);

    expect($published->status)->toBe('published')
        ->and($published->sourceLocale())->toBe($locale)
        ->and($published->published_at->equalTo(now()))->toBeTrue();
    $audit = GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->sole();
    expect($audit->actor_id)->toBe($this->admin->id)
        ->and($audit->actor_type)->toBe('admin')
        ->and($audit->before_state)->toBe(['status' => 'pending_review'])
        ->and($audit->after_state)->toBe(['status' => 'published']);
})->with(['en', 'es', 'it'])->with([null, 'pending', 'stale', 'failed']);

it('refuses missing canonical fields even when a derivative has valid content', function (string $locale, string $missing, ?string $value) {
    $tour = publicationInvariantTour($locale);
    $source = $tour->translations()->where('locale', $locale)->firstOrFail();
    if ($missing === 'row') {
        $source->delete();
    } else {
        $source->update([$missing => $value]);
    }
    $tour->translations()->create([
        'locale' => $tour->derivativeLocales()[0],
        'title' => 'Derivative title', 'description' => 'Derivative description.',
    ]);

    try {
        app(ApproveTourAction::class)->execute($this->admin, $tour);
        $this->fail('Expected publication to reject the missing canonical content.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);
    }
    expect($tour->fresh()->status)->toBe('pending_review')
        ->and($tour->fresh()->published_at)->toBeNull()
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(0);
})->with(['en', 'es', 'it'])->with([
    ['row', null], ['title', ''], ['title', '   '],
    ['description', null], ['description', ''], ['description', '   '],
]);

it('refuses a repeated approval from a stale tour without duplicate effects', function () {
    $tour = publicationInvariantTour();
    $stale = Tour::findOrFail($tour->id);
    $published = app(ApproveTourAction::class)->execute($this->admin, $tour);
    $stamp = $published->published_at;
    $this->travel(1)->hour();

    try {
        app(ApproveTourAction::class)->execute($this->admin, $stale);
        $this->fail('A second approval must not create a second publication.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);
    }
    expect($tour->fresh()->published_at->equalTo($stamp))->toBeTrue()
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->count())->toBe(1);
});

it('approves each source locale through the real Filament individual action', function (string $locale) {
    $tour = publicationInvariantTour($locale);

    Livewire::test(ListTours::class)
        ->callTableAction('publish', $tour)
        ->assertHasNoTableActionErrors();

    expect($tour->fresh()->status)->toBe('published')
        ->and($tour->fresh()->published_at)->not->toBeNull()
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->count())->toBe(1);
})->with(['en', 'es', 'it']);

it('retains the first publication timestamp on a later authorized approval', function () {
    $tour = publicationInvariantTour();
    $firstPublished = now()->subMonth()->startOfSecond();
    $tour->update(['published_at' => $firstPublished]);

    app(ApproveTourAction::class)->execute($this->admin, $tour);

    expect($tour->fresh()->published_at->equalTo($firstPublished))->toBeTrue()
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->count())->toBe(1);
});

it('rolls publication back if the governance audit cannot be recorded', function () {
    $tour = publicationInvariantTour();
    $audit = Mockery::mock(GovernanceAuditService::class);
    $audit->shouldReceive('log')->once()->andThrow(new RuntimeException('Audit unavailable'));
    $this->app->instance(GovernanceAuditService::class, $audit);

    expect(fn () => app(ApproveTourAction::class)->execute($this->admin, $tour))
        ->toThrow(RuntimeException::class, 'Audit unavailable');

    expect($tour->fresh()->status)->toBe('pending_review')
        ->and($tour->fresh()->published_at)->toBeNull()
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(0);
});

it('rechecks a revoked permission or suspended actor instead of trusting loaded state', function (string $revocation) {
    $tour = publicationInvariantTour();
    $actor = $this->admin->fresh('adminPermission');
    if ($revocation === 'permission') {
        $this->admin->adminPermission()->update(['flags' => ['manage_tours' => false]]);
    } else {
        User::whereKey($actor->id)->update(['status' => 'suspended']);
    }

    expect(fn () => app(ApproveTourAction::class)->execute($actor, $tour))
        ->toThrow(AuthorizationException::class);
    expect($tour->fresh()->status)->toBe('pending_review')
        ->and($tour->fresh()->published_at)->toBeNull()
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(0);
})->with(['permission', 'suspension']);

it('publishes only eligible authorized items in a mixed Filament bulk request', function (bool $deniedFirst) {
    $denied = $deniedFirst ? publicationInvariantTour() : null;
    $valid = publicationInvariantTour('es');
    $missingSource = publicationInvariantTour('it');
    $missingSource->translations()->delete();
    $unapprovedOwner = publicationInvariantTour();
    $unapprovedOwner->partnerRecord()->update(['onboarding_status' => 'pending', 'is_active' => false]);
    $denied ??= publicationInvariantTour();
    Gate::before(function (User $actor, string $ability, array $arguments) use ($denied) {
        if ($ability === 'publish' && ($arguments[0] ?? null) instanceof Tour && $arguments[0]->id === $denied->id) {
            return false;
        }

    });

    Livewire::test(ListTours::class)
        ->callTableBulkAction('bulk_publish', [$valid, $missingSource, $unapprovedOwner, $denied]);

    expect($valid->fresh()->status)->toBe('published');
    foreach ([$missingSource, $unapprovedOwner, $denied] as $rejected) {
        expect($rejected->fresh()->status)->toBe('pending_review')
            ->and($rejected->fresh()->published_at)->toBeNull()
            ->and(GovernanceAuditLog::where('target_id', $rejected->id)->count())->toBe(0);
    }
    expect(GovernanceAuditLog::where('action', 'tour.publish')->count())->toBe(1);
})->with([false, true]);

it('rolls back destructive source edits and metadata together on a published tour', function (string $locale, string $change) {
    $tour = publicationInvariantTour($locale);
    app(ApproveTourAction::class)->execute($this->admin, $tour);
    $stamp = $tour->fresh()->published_at;

    Livewire::test(EditTour::class, ['record' => $tour->id])
        ->fillForm(function (array $state) use ($change, $locale) {
            $translations = $state['translations'];
            foreach ($translations as $key => $translation) {
                if ($translation['locale'] !== $locale) {
                    continue;
                }
                if ($change === 'delete') {
                    unset($translations[$key]);
                } elseif ($change === 'rename') {
                    $translations[$key]['locale'] = $locale === 'en' ? 'es' : 'en';
                } else {
                    $translations[$key]['description'] = '';
                }
            }

            return ['location' => 'Must roll back', 'translations' => $translations];
        })
        ->call('save');

    $source = $tour->translations()->where('locale', $locale)->first();
    expect($source)->not->toBeNull()
        ->and($source->description)->toBe('Canonical description.')
        ->and($tour->fresh()->location)->toBe('Rome')
        ->and($tour->fresh()->status)->toBe('published')
        ->and($tour->fresh()->published_at->equalTo($stamp))->toBeTrue()
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->count())->toBe(1);
})->with(['en', 'es', 'it'])->with(['delete', 'rename', 'clear description']);

it('preserves a valid published metadata save without republishing', function (string $locale) {
    $tour = publicationInvariantTour($locale);
    app(ApproveTourAction::class)->execute($this->admin, $tour);
    $stamp = $tour->fresh()->published_at;

    Livewire::test(EditTour::class, ['record' => $tour->id])
        ->fillForm(['location' => 'Florence'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tour->fresh()->location)->toBe('Florence')
        ->and($tour->fresh()->status)->toBe('published')
        ->and($tour->fresh()->sourceLocale())->toBe($locale)
        ->and($tour->fresh()->published_at->equalTo($stamp))->toBeTrue()
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->count())->toBe(1);
})->with(['en', 'es', 'it']);

it('rejects a forced form publication without persisting source or metadata changes', function (string $locale) {
    $tour = publicationInvariantTour($locale, 'draft');

    Livewire::test(EditTour::class, ['record' => $tour->id])
        ->fillForm(function (array $state) {
            $translations = $state['translations'];
            foreach ($translations as $key => $translation) {
                $translations[$key]['title'] = 'Must roll back';
            }

            return ['status' => 'published', 'location' => 'Must roll back', 'translations' => $translations];
        })
        ->call('save');

    expect($tour->fresh()->status)->toBe('draft')
        ->and($tour->fresh()->location)->toBe('Rome')
        ->and($tour->translations()->where('locale', $locale)->value('title'))->toBe('Canonical title')
        ->and($tour->fresh()->published_at)->toBeNull()
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(0);
})->with(['en', 'es', 'it']);

it('prevents a stale published edit form from republishing an unpublished tour', function () {
    $tour = publicationInvariantTour();
    app(ApproveTourAction::class)->execute($this->admin, $tour);
    $stamp = $tour->fresh()->published_at;
    $component = Livewire::test(EditTour::class, ['record' => $tour->id]);
    app(UnpublishTourAction::class)->execute($this->admin, $tour->fresh());

    $component->fillForm(['location' => 'Must roll back'])->call('save');

    expect($tour->fresh()->status)->toBe('draft')
        ->and($tour->fresh()->location)->toBe('Rome')
        ->and($tour->fresh()->published_at->equalTo($stamp))->toBeTrue()
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->count())->toBe(1)
        ->and(GovernanceAuditLog::where('action', 'tour.unpublish')->where('target_id', $tour->id)->count())->toBe(1);
});

it('preserves existing nonblank admin source edits without choosing a derivative policy', function (string $locale) {
    $tour = publicationInvariantTour($locale);
    app(ApproveTourAction::class)->execute($this->admin, $tour);

    Livewire::test(EditTour::class, ['record' => $tour->id])
        ->fillForm(function (array $state) use ($locale) {
            $translations = $state['translations'];
            foreach ($translations as $key => $translation) {
                if ($translation['locale'] === $locale) {
                    $translations[$key]['description'] = 'Existing permitted source edit.';
                }
            }

            return ['translations' => $translations];
        })
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tour->translations()->where('locale', $locale)->value('description'))->toBe('Existing permitted source edit.')
        ->and($tour->fresh()->sourceLocale())->toBe($locale)
        ->and($tour->fresh()->status)->toBe('published')
        ->and(GovernanceAuditLog::where('action', 'tour.publish')->where('target_id', $tour->id)->count())->toBe(1);
})->with(['en', 'es', 'it']);

it('prevents terminal or published tours from returning to review through partner submission', function (string $status) {
    $tour = publicationInvariantTour('it', $status);

    try {
        app(TourService::class)->submitForReview($tour);
        $this->fail('Invalid review resubmission must be rejected.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);
    }
    expect($tour->fresh()->status)->toBe($status)
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(0);
})->with(['published', 'archived']);

it('still validates canonical content on a duplicate review submission', function (string $locale) {
    $tour = publicationInvariantTour($locale);
    $tour->translations()->where('locale', $locale)->update(['description' => '   ']);

    expect(fn () => app(TourService::class)->submitForReview($tour))->toThrow(HttpException::class);

    expect($tour->fresh()->status)->toBe('pending_review')
        ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(0);
})->with(['en', 'es', 'it']);
