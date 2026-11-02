<?php

use App\Domains\Blog\Actions\UpdateBlogPostAction;
use App\Domains\Blog\Models\BlogPost;
use App\Domains\Translation\Contracts\ContentTranslatorContract;
use App\Domains\Translation\Jobs\TranslateContentFieldJob;
use App\Domains\Translation\Models\TranslationFieldState;
use App\Domains\Translation\Services\AutoTranslationService;
use App\Domains\Translation\Services\TranslationHealthCheckService;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Phase H: automatic field-level translation engine (T-H01..T-H06)
|--------------------------------------------------------------------------
| Tests the AutoTranslationService orchestration, TranslateContentFieldJob
| workers, TranslationQualityValidator gating, manual-edit protection and
| the TranslationHealthCheckService rollups.
*/

if (! function_exists('makeTranslationAdmin')) {
    function makeTranslationAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $admin->adminPermission()->create(['flags' => ['manage_translations' => true, 'manage_blog' => true, 'manage_cms' => true]]);
        $admin->authorProfile()->create([
            'display_name' => ['en' => 'Translation Admin', 'es' => 'Admin de Traducción'],
            'bio' => ['en' => 'Translation Bio', 'es' => 'Bio Traducción'],
            'avatar_url' => 'https://r2.example.com/avatars/translation-admin.jpg',
            'is_active' => true,
        ]);

        return $admin->fresh(['adminPermission', 'authorProfile']);
    }
}

if (! function_exists('autoTranslationPayload')) {
    /**
     * English-only save payload. ES/IT stay empty (missing) so the engine has
     * to queue/translate them; pass overrides to customize.
     *
     * @return array<string, mixed>
     */
    function autoTranslationPayload(User $admin, array $overrides = []): array
    {
        return array_merge([
            'author_id' => $admin->id,
            'blog_category_id' => makeBlogCategory()->id,
            'slug' => 'translation-' . uniqid(),
            'title' => ['en' => 'A Really Long English Source Title For Echo Checks'],
            'excerpt' => ['en' => 'A really long English excerpt line for echo checks.'],
            'body' => ['en' => '<p>Fresh English body with plenty of words for the translation engine.</p>'],
            'meta_title' => ['en' => 'Fresh English meta title for the engine'],
            'meta_description' => ['en' => 'Fresh English meta description for the translation engine checks.'],
            'status' => 'draft',
        ], $overrides);
    }
}

if (! function_exists('swapTranslationEngine')) {
    /**
     * Swap a controllable fake translator. Behaviour maps field_name to:
     * 'echo' | 'empty' | 'corrupt-html' | 'overlength' (anything else falls
     * back to a valid deterministic translation).
     */
    function swapTranslationEngine(array $behaviour = []): void
    {
        $engine = new class($behaviour) implements ContentTranslatorContract
        {
            public function __construct(private array $behaviour) {}

            public function translateField(string $sourceText, string $targetLocale, string $fieldName, bool $isHtml = false): array
            {
                $text = match ($this->behaviour[$fieldName] ?? null) {
                    'echo' => $sourceText,
                    'empty' => '   ',
                    'corrupt-html' => '<div></div> <div> </div>',
                    'overlength' => str_repeat('Título considerablemente largo para exceder el límite SEO ', 4),
                    default => $isHtml
                        ? "<p>Traducido {$fieldName} en el cuerpo del contenido</p>"
                        : "Traducción {$fieldName} para el contenido de destino",
                };

                return [
                    'translated_text' => $text,
                    'provider' => 'fake-translator',
                    'model' => 'test-model',
                ];
            }
        };

        app()->instance(ContentTranslatorContract::class, $engine);
    }
}

if (! function_exists('translationState')) {
    function translationState(string $modelType, BlogPost $post, string $locale, string $field): TranslationFieldState
    {
        return TranslationFieldState::query()
            ->where('translatable_type', $modelType)
            ->where('translatable_id', $post->id)
            ->where('locale', $locale)
            ->where('field_name', $field)
            ->firstOrFail();
    }
}

// 1. Saving English content queues field-level translation jobs with hashes.
it('queues field-level translation jobs per locale and field when English content changes', function () {
    Queue::fake();

    $admin = makeTranslationAdmin();

    $post = app(UpdateBlogPostAction::class)->execute(
        $admin,
        autoTranslationPayload($admin),
    )->fresh();

    // 5 translatable fields × 2 target locales = 10 queue entries.
    $jobs = Queue::pushed(TranslateContentFieldJob::class);

    expect($jobs->count())->toBe(10)
        ->and(TranslationFieldState::query()
            ->where('translatable_type', 'blog_post')
            ->where('translatable_id', $post->id)
            ->where('locale', 'es')
            ->count())->toBe(5);

    $state = translationState('blog_post', $post, 'es', 'body');

    expect($state->status)->toBe(TranslationFieldState::STATUS_QUEUED)
        ->and($state->origin)->toBe(TranslationFieldState::ORIGIN_AUTO)
        ->and($state->source_hash)->toBe(
            app(AutoTranslationService::class)->normalizeHash($post->getAttribute('body')['en'])
        )->and($state->translated_source_hash)->toBeNull();
});

// 2. The worker translates only its own field without clobbering manually
//    authored sibling text, and records provenance hashes.
it('translates only the field under test and keeps manually authored siblings unchanged', function () {
    swapTranslationEngine();
    $admin = makeTranslationAdmin();

    $post = app(UpdateBlogPostAction::class)->execute(
        $admin,
        autoTranslationPayload($admin, [
            'title' => ['en' => 'A Really Long English Source Title For Echo Checks', 'es' => 'Título Manual Editado'],
        ]),
    )->fresh();

    expect($post->getAttribute('title')['es'])->toBe('Título Manual Editado')
        ->and($post->getAttribute('body')['es'])->toBe('<p>Traducido body en el cuerpo del contenido</p>')
        ->and($post->getAttribute('body')['it'])->toContain('<p>Traducido body')
        ->and($post->getAttribute('meta_description')['es'])->toBe('Traducción meta_description para el contenido de destino');

    $manual = translationState('blog_post', $post, 'es', 'title');
    expect($manual->origin)->toBe(TranslationFieldState::ORIGIN_MANUAL)
        ->and($manual->status)->toBe(TranslationFieldState::STATUS_MANUALLY_EDITED)
        ->and($manual->manually_edited_by)->toBe($admin->id)
        ->and($manual->translated_source_hash)->toBe(
            app(AutoTranslationService::class)->normalizeHash($post->getAttribute('title')['en'])
        );

    $completed = translationState('blog_post', $post, 'it', 'body');
    expect($completed->status)->toBe(TranslationFieldState::STATUS_COMPLETED)
        ->and($completed->provider)->toBe('fake-translator')
        ->and($completed->model)->toBe('test-model')
        ->and($completed->translated_hash)->toBe(
            app(AutoTranslationService::class)->normalizeHash($post->getAttribute('body')['it'])
        );
});

// 3. Manual translations are preserved when EN drifts: flagged needs_review,
//    never overwritten. Auto fields get re-queued for the new source.
it('flags manual translations for review on English drift instead of overwriting them', function () {
    $admin = makeTranslationAdmin();

    $post = app(UpdateBlogPostAction::class)->execute(
        $admin,
        autoTranslationPayload($admin, [
            'title' => ['en' => 'A Really Long English Source Title For Echo Checks', 'es' => 'Título Manual Editado'],
        ]),
    )->fresh();

    // Editor changes the English title; the ES manual title stays untouched.
    app(UpdateBlogPostAction::class)->execute(
        $admin,
        ['title' => ['en' => 'A Changed And Longer English Source Title For Review']],
        $post,
    );

    $state = translationState('blog_post', $post->fresh(), 'es', 'title');

    expect($state->status)->toBe(TranslationFieldState::STATUS_NEEDS_REVIEW)
        ->and($state->origin)->toBe(TranslationFieldState::ORIGIN_MANUAL)
        ->and($post->fresh()->getAttribute('title')['es'])->toBe('Título Manual Editado');
});

// 4. Validator gate: empty and echo-back output fail with error codes,
//    corrupted HTML fails, an over-long meta_title lands in needs_review.
it('rejects validator failures to failed/needs_review statuses with error codes', function () {
    swapTranslationEngine([
        'title' => 'empty',
        'meta_description' => 'echo',
        'body' => 'corrupt-html',
        'meta_title' => 'overlength',
    ]);

    $admin = makeTranslationAdmin();

    $post = app(UpdateBlogPostAction::class)->execute(
        $admin,
        autoTranslationPayload($admin),
    )->fresh();

    $empty = translationState('blog_post', $post, 'es', 'title');
    expect($empty->status)->toBe(TranslationFieldState::STATUS_FAILED)
        ->and($empty->last_error_code)->toBe('EMPTY_TRANSLATION');

    $echo = translationState('blog_post', $post, 'es', 'meta_description');
    expect($echo->status)->toBe(TranslationFieldState::STATUS_FAILED)
        ->and($echo->last_error_code)->toBe('UNCHANGED_SOURCE_RETURNED');

    $corrupt = translationState('blog_post', $post, 'es', 'body');
    expect($corrupt->status)->toBe(TranslationFieldState::STATUS_FAILED)
        ->and($corrupt->last_error_code)->toBe('CORRUPTED_HTML')
        ->and($post->fresh()->getAttribute('body')['es'] ?? null)->toBeNull();

    $overLength = translationState('blog_post', $post, 'es', 'meta_title');
    expect($overLength->status)->toBe(TranslationFieldState::STATUS_NEEDS_REVIEW)
        ->and($overLength->last_error_code)->toBe('OVER_LENGTH_SEO_FIELD')
        ->and($post->fresh()->getAttribute('meta_title')['es'] ?? null)->not->toBeNull();

    $healthy = translationState('blog_post', $post, 'es', 'excerpt');
    expect($healthy->status)->toBe(TranslationFieldState::STATUS_COMPLETED);
});

// 5. Field-level granularity: only the failing field is retried; completed
//    fields keep their completed status.
it('retries only failed fields and leaves completed fields untouched', function () {
    swapTranslationEngine([
        'meta_title' => 'echo',
    ]);

    $admin = makeTranslationAdmin();

    $post = app(UpdateBlogPostAction::class)->execute(
        $admin,
        autoTranslationPayload($admin),
    )->fresh();

    Queue::fake();
    $retry = app(TranslationHealthCheckService::class)->retryFailed('blog_post', $post->id);

    expect($retry['queued'])->toBe(2)
        ->and(Queue::pushed(TranslateContentFieldJob::class)->count())->toBe(2)
        ->and(translationState('blog_post', $post, 'es', 'meta_title')->status)
        ->toBe(TranslationFieldState::STATUS_QUEUED)
        ->and(translationState('blog_post', $post, 'es', 'title')->status)
        ->toBe(TranslationFieldState::STATUS_COMPLETED);
});

// 6. Health dashboard rollups + EN drift marks auto fields for re-translation.
it('aggregates translation health counts across model types for the dashboard', function () {
    swapTranslationEngine([
        'excerpt' => 'echo',
    ]);

    $admin = makeTranslationAdmin();

    $postA = app(UpdateBlogPostAction::class)->execute($admin, autoTranslationPayload($admin))->fresh();
    $postB = app(UpdateBlogPostAction::class)->execute($admin, autoTranslationPayload($admin))->fresh();

    $summary = app(TranslationHealthCheckService::class)->platformSummary();

    // 2 posts × 5 fields × 2 locales = 20 tracked fields; 2 failures per
    // post (es+it excerpt echo) = 4 failed, 16 completed.
    expect($summary['total_fields'])->toBe(20)
        ->and($summary['counts'][TranslationFieldState::STATUS_FAILED])->toBe(4)
        ->and($summary['counts'][TranslationFieldState::STATUS_COMPLETED])->toBe(16)
        ->and(round($summary['failure_rate'], 4))->toBe(0.2);

    $blogRow = collect($summary['by_model_type'])->firstWhere('model_type', BlogPost::class);

    expect($blogRow)->not->toBeNull()
        ->and($blogRow['total_fields'])->toBe(20)
        ->and($blogRow['counts'][TranslationFieldState::STATUS_FAILED])->toBe(4);

    // An EN source update re-queues exactly the ES/IT body fields.
    Queue::fake();

    app(UpdateBlogPostAction::class)->execute(
        $admin,
        ['body' => ['en' => '<p>Updated English body content for the second pass of translation checks.</p>']],
        $postB,
    );

    $jobs = Queue::pushed(TranslateContentFieldJob::class);

    expect($jobs->count())->toBe(2)
        ->and($jobs->pluck('fieldName')->map(fn ($v) => $v)->unique())->each->toBe('body')
        ->and(translationState('blog_post', $postB, 'es', 'body')->status)
        ->toBe(TranslationFieldState::STATUS_QUEUED);
});
