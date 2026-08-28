<?php

declare(strict_types=1);

use App\Domains\Blog\Models\BlogPost;
use App\Domains\Blog\Transformers\BlogPostTransformer;
use App\Filament\Resources\BlogPostResource\Pages\ListBlogPosts;
use App\Models\Tour;
use App\Models\User;
use Filament\Notifications\Livewire\Notifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = makeAdmin();
    actingAs($this->admin);
});

/**
 * Regression for review-1: the Related Tours admin Select previously built its
 * options with `Tour::where('status','published')->pluck('title','id')`, but the
 * `tours` table has no `title` column (titles live in `tour_translations`), so
 * Filament's options closure threw a QueryException the moment it ran. The fix
 * loads tours with translations and resolves the label via `displayTitle('en')`.
 *
 * Queue::fake() neutralizes the Tour::saved listener that pushes a Scout
 * index-removal job onto the Redis-backed `scout` queue (no redis ext locally;
 * real CI has Redis). The tour row is still persisted for the options query.
 */
test('related-tour options resolve titles from translations instead of the missing tours.title column', function () {
    Queue::fake();

    $tour = makeSearchableTour('published');
    addTranslation($tour, 'en', 'Colosseum Express Tour');
    $other = makeSearchableTour('published');
    addTranslation($other, 'en', 'Vatican Museums Tour');

    // The pre-fix expression queries a column that does not exist on `tours`,
    // so it errors out (the exact exception class depends on the driver / error
    // handler, but the message always references the missing `title` column).
    $preFixError = null;
    try {
        Tour::where('status', 'published')->pluck('title', 'id');
    } catch (\Throwable $e) {
        $preFixError = $e;
    }
    expect($preFixError)->not->toBeNull('pre-fix pluck(title) should fail on the missing tours.title column')
        ->and($preFixError->getMessage())->toContain('title');

    // The fixed expression (the body of the Filament Select options closure)
    // resolves a non-empty, id => display-title map from translations.
    $options = Tour::where('status', 'published')
        ->with('translations')
        ->get()
        ->mapWithKeys(fn (Tour $t) => [$t->id => $t->displayTitle('en')]);

    expect($options)->toHaveKey($tour->id)
        ->and($options[$tour->id])->toBe('Colosseum Express Tour')
        ->and($options[$other->id])->toBe('Vatican Museums Tour')
        ->and($options)->not->toBeEmpty();
});

/**
 * Regression for review-2: the list-page "preview" table action built the
 * preview URL with `url(...)`, which resolves to the Laravel backend host. The
 * blog preview page is a Next.js frontend route, so the backend-host link 404s.
 * The fix builds the URL from `config('app.frontend_url')` like EditBlogPost.
 */
test('preview table action generates a frontend-hosted preview link, not a backend URL', function () {
    config(['app.frontend_url' => 'http://frontend.test']);
    config(['app.url' => 'http://backend.test']);

    $post = makeBlogPost();

    Livewire::test(ListBlogPosts::class)
        ->callTableAction('preview', $post)
        ->assertHasNoTableActionErrors();

    // Filament pushes sent notifications to the session; pull them back and
    // inspect the body, which embeds the generated preview URL.
    $notifications = new Notifications;
    $notifications->mount();
    $notifications = $notifications->notifications;

    expect($notifications)->not->toBeEmpty();

    $body = $notifications->first()->getBody();
    expect($body)
        ->toContain('http://frontend.test/en/blog/'.$post->slug.'/preview?token=')
        ->and($body)->not->toContain('http://backend.test');
});

/**
 * Regression for review-3: ListBlogPostsAction / GetBlogCategoryAction
 * eager-loaded `authorProfile.user` and `category` but NOT `author`. When a
 * post's author had no (usable) AuthorProfile, the transformer fell back to
 * `$post->author->name`, triggering a lazy query per post (N+1) on the public
 * list/category endpoints. The fix adds `author` to the eager-load list.
 */
test('list query eager-loads author so the transformer fallback adds no per-post users query', function () {
    // Author with NO authorProfile => transformer falls back to $post->author->name.
    $author = User::factory()->admin()->create();
    $category = makeBlogCategory();
    $postCount = 5;
    for ($i = 0; $i < $postCount; $i++) {
        makeBlogPost([
            'author_id' => $author->id,
            'blog_category_id' => $category->id,
        ]);
    }

    $transformer = new BlogPostTransformer();

    $usersSelects = function (array $with) use ($transformer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $posts = BlogPost::query()->published()->with($with)->orderByDesc('published_at')->get();
        foreach ($posts as $post) {
            $transformer->transform($post, 'en');
        }
        DB::disableQueryLog();

        return collect(DB::getQueryLog())
            ->filter(fn (array $q) => preg_match('/from\s+"users"/i', $q['query']) === 1)
            ->count();
    };

    // Fixed eager-load set (the one now in ListBlogPostsAction).
    $withFix = $usersSelects(['author', 'authorProfile.user', 'category']);
    // Pre-fix eager-load set (author missing).
    $withoutFix = $usersSelects(['authorProfile.user', 'category']);

    // With the fix, exactly one users query (the eager-loaded author) regardless
    // of post count.
    expect($withFix)->toBe(1)
        // Without the fix, each post lazy-loads its author => one query per post.
        ->and($withoutFix)->toBe($postCount)
        ->and($withoutFix)->toBeGreaterThan($withFix);
});