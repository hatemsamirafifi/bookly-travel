# Quickstart: Blog and Travel Insights

**Feature**: 016-blog-travel-insights | **Date**: 2026-08-22

## Prerequisites

- PHP 8.3+, Composer, Laravel 11
- Node.js 20+, npm
- PostgreSQL 15+ (with JSONB support)
- Redis (cache + queue)
- Existing Bookly backend + frontend running (specs 010–015 implemented)

## Backend Setup

### 1. Run migrations

```bash
cd backend
php artisan migrate
```

Creates 5 new tables: `blog_categories`, `blog_posts`, `author_profiles`, `blog_post_tours`,
and adds `manage_blog` to the admin permission flags.

### 2. Add environment variables

Add to `backend/.env`:

```env
PREVIEW_KEY=base64:your-random-32-byte-key-here
```

Generate with: `php artisan key:generate --name=PREVIEW_KEY` or `openssl rand -base64 32`.

### 3. Register rate limiters

Add to `backend/app/Providers/AppServiceProvider.php` (in the `boot()` method, alongside existing
limiters):

```php
RateLimiter::for('blog', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
RateLimiter::for('blog_detail', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
```

### 4. Register policy + morph map

Add to `AppServiceProvider::boot()`:

```php
Gate::policy(BlogPost::class, BlogPostPolicy::class);
Gate::policy(BlogCategory::class, BlogCategoryPolicy::class);
// In the morph map:
'blog_post' => BlogPost::class,
'blog_category' => BlogCategory::class,
```

### 5. Start the queue worker

```bash
php artisan queue:work --queue=default
```

The queue processes: `InvalidateBlogCacheJob`, `RegenerateSitemapJob`,
`PublishScheduledBlogPostJob`.

### 6. Run backend tests

```bash
php artisan test --filter=Blog
```

Expected: all `tests/Feature/Blog/*` tests pass (BlogPostTest, BlogListTest, BlogCategoryTest,
BlogPreviewTest, BlogSitemapTest, BlogAuthorizationTest).

## Frontend Setup

### 1. Add i18n keys

Add a `"blog"` namespace to all three message files:

- `frontend/messages/en.json`
- `frontend/messages/es.json`
- `frontend/messages/it.json`

```json
{
  "blog": {
    "title": "Travel Insights",
    "emptyState": "No posts yet",
    "browseTours": "Browse Tours",
    "readingTime": "{minutes} min read",
    "partialTranslation": "This article is not yet available in your language. Showing English content.",
    "previewBanner": "Preview mode — this post is not yet published.",
    "previewExpired": "Preview link expired.",
    "category": "Category",
    "publishedOn": "Published on {date}",
    "relatedTours": "Related Tours",
    "relatedPosts": "Related Posts"
  }
}
```

### 2. Run the frontend

```bash
cd frontend
npm install
npm run dev
```

### 3. Verify blog routes

- Blog listing: `http://localhost:3000/en/blog`
- Blog detail: `http://localhost:3000/en/blog/{slug}`
- Category: `http://localhost:3000/en/blog/category/{slug}`
- Preview: `http://localhost:3000/en/blog/{slug}/preview?token={signed}`

### 4. Run frontend tests

```bash
npm run build         # TypeScript + build check
npm run lint          # ESLint
npm run typecheck     # tsc --noEmit
npm run test          # Jest unit tests
npm run test:e2e -- --grep "blog"   # Playwright E2E
npm run test:a11y     # axe-core a11y
```

### 5. Run Lighthouse SEO audit

```bash
npm run lighthouse  # then manually check /en/blog and /en/blog/{slug}
```

Expected: Performance ≥ 90, Accessibility ≥ 95, SEO score with zero missing required tags.

## Admin (Filament) Setup

### 1. Grant `manage_blog` permission

In the Filament admin panel (or via tinker):

```php
$user = User::where('role', 'admin')->first();
$perm = $user->adminPermission;
$flags = $perm->flags;
$flags['manage_blog'] = true;
$perm->update(['flags' => $flags]);
```

### 2. Create a blog category

Navigate to Filament → Content → Blog Categories → Create.

### 3. Create a blog post

Navigate to Filament → Content → Blog Posts → Create:
- Enter slug (auto-generated from EN title)
- Select category + author
- Write EN title + body (RichEditor)
- Optionally add ES/IT translations
- Upload cover image
- Attach related tours
- Save as draft → Generate preview token → Preview → Publish

## Sitemap Verification

```bash
curl http://localhost:8000/sitemap.xml | grep -c "/blog/"
```

Published blog posts + categories should appear with hreflang alternates. Drafts, scheduled-future,
and archived posts should be absent.