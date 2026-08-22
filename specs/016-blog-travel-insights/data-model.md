# Data Model: Blog and Travel Insights

**Feature**: 016-blog-travel-insights | **Date**: 2026-08-22

All persistent data lives in the Laravel backend (PostgreSQL + JSONB). The frontend defines view
models (data shapes consumed by components) backed by the API contract. No frontend database.

---

## 1. Blog Post

The editorial article entity.

### Migration: `blog_posts` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | `bigint` (PK) | no | auto | |
| `slug` | `varchar(120)` | no | — | Unique; `^[a-z0-9-]+$`; generated via `Str::slug()` from EN title |
| `status` | `varchar(20)` | no | `'draft'` | `draft` / `published` / `archived` |
| `title` | `jsonb` | no | — | Localized: `{ en: string, es: string|null, it: string|null }` |
| `body` | `jsonb` | no | — | Localized HTML (Filament RichEditor output) |
| `excerpt` | `jsonb` | yes | — | Localized; auto-generated from body if not set |
| `meta_description` | `jsonb` | yes | — | Localized; falls back to excerpt |
| `cover_image_url` | `varchar(255)` | yes | — | R2 CDN URL |
| `is_featured` | `boolean` | no | `false` | Editorial curation for listing hero |
| `scheduled_at` | `timestamp` | yes | — | Future publish time; visible iff `<= now()` |
| `published_at` | `timestamp` | yes | — | Stamped on first publication; preserved on re-publish |
| `author_id` | `bigint` (FK→`users.id`) | no | — | The admin user who authored the post |
| `blog_category_id` | `bigint` (FK→`blog_categories.id`) | no | — | The post's category |
| `created_at` | `timestamp` | no | now() | |
| `updated_at` | `timestamp` | no | now() | |

**Indexes**:
- `UNIQUE(slug)`
- `INDEX(status, is_featured)` — featured query on listing
- `INDEX(status, scheduled_at)` — public visibility scope
- `INDEX(status, published_at)` — newest-first ordering + 410 check
- `INDEX(author_id)` — author lookups
- `INDEX(blog_category_id)` — category filter

**Foreign keys**:
- `author_id` → `users.id` (restrict on delete — authors cannot be deleted while posts exist)
- `blog_category_id` → `blog_categories.id` (cascade on delete — removing a category orphans posts; admin must reassign first)

### Model: `BlogPost`

```php
protected $table = 'blog_posts';
public const LOCALES = ['en', 'es', 'it'];
public const STATUS_DRAFT = 'draft';
public const STATUS_PUBLISHED = 'published';
public const STATUS_ARCHIVED = 'archived';

protected $fillable = [
    'slug', 'status', 'title', 'body', 'excerpt', 'meta_description',
    'cover_image_url', 'is_featured', 'scheduled_at', 'published_at',
    'author_id', 'blog_category_id',
];

protected $casts = [
    'title' => 'array',
    'body' => 'array',
    'excerpt' => 'array',
    'meta_description' => 'array',
    'is_featured' => 'boolean',
    'scheduled_at' => 'datetime',
    'published_at' => 'datetime',
    'author_id' => 'integer',
    'blog_category_id' => 'integer',
];
```

### Relationships

- `author()` → `belongsTo(User::class, 'author_id')`
- `category()` → `belongsTo(BlogCategory::class, 'blog_category_id')`
- `relatedTours()` → `belongsToMany(Tour::class, 'blog_post_tours')->withPivot('sort_order')->orderByPivot('sort_order')`
- `authorProfile()` → `hasOneThrough(AuthorProfile::class, User::class, 'id', 'user_id', 'author_id', 'id')` (or access via `$post->author->authorProfile`)

### Scopes

- `scopePublished($query)` → `where('status', 'published')->where(function ($q) { $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()); })`
- `scopeFeatured($query)` → `where('is_featured', true)`

### Localized accessor (mirrors `StaticPage::contentFor()`)

```php
public function contentFor(string $field, string $locale): ?string
{
    $values = $this->{$field} ?? [];
    return $values[$locale]
        ?? $values['en']
        ?? collect($values)->first(fn ($v) => filled($v));
}
```

### Validation rules

- `slug`: `required|string|max:120|regex:/^[a-z0-9-]+$/|unique:blog_posts,slug,{id}`
- `status`: `required|in:draft,published,archived`
- `title.en`: `required|string` (EN required to publish)
- `body.en`: `required|string` (EN required to publish)
- `title.es`, `title.it`, `body.es`, `body.it`: `nullable|string`
- `scheduled_at`: `nullable|date|after_or_equal:today`
- `blog_category_id`: `required|exists:blog_categories,id`
- `author_id`: `required|exists:users,id`

### State transitions

```text
                  ┌──────────┐
    create ──→    │  draft   │
                  └────┬─────┘
                       │ publish (status→published, published_at ?? now())
                       ├──────────────────────────────┐
                       │                              │
                       ▼                              │
                  ┌──────────┐    archive             │
                  │published │ ──────────→ ┌────────┐ │
                  └────┬─────┘             │archived│ │
                       │ re-publish         └───┬────┘ │
                       │ (published_at preserved)  │    │
                       │                           │    │
                       ▼                           │    │
                  ┌──────────┐   unarchive         │    │
                  │published │ ←──────────────────┘    │
                  └──────────┘                        │
                                                      │
                  ┌──────────┐                        │
                  │  draft   │ ←── unarchive (never   │
                  └──────────┘     published → draft)  │
```

**Visibility rule**: A post is publicly visible iff `status = 'published' AND (scheduled_at IS NULL OR scheduled_at <= now())`.

**Error mapping** (public detail request):
- Non-existent slug → **404**
- `status = 'draft'` → **404**
- `status = 'published'` + `scheduled_at > now()` → **404**
- `status = 'archived'` + `published_at IS NULL` (never published) → **404**
- `status = 'archived'` + `published_at IS NOT NULL` (was published) → **410 Gone**

---

## 2. Blog Category

Taxonomy grouping blog posts. Separate from tour `categories`.

### Migration: `blog_categories` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | `bigint` (PK) | no | auto | |
| `name` | `varchar(100)` | no | — | Non-localized (mirrors tour Category) |
| `slug` | `varchar(120)` | no | — | Unique; `^[a-z0-9-]+$` |
| `description` | `text` | yes | — | Non-localized |
| `is_active` | `boolean` | no | `true` | Inactive categories excluded from nav list |
| `display_order` | `integer` | no | `0` | Sort order in nav |
| `created_at` | `timestamp` | no | now() | |
| `updated_at` | `timestamp` | no | now() | |

**Indexes**:
- `UNIQUE(slug)`
- `INDEX(is_active, display_order)` — nav list query

### Model: `BlogCategory`

```php
protected $table = 'blog_categories';
protected $fillable = ['name', 'slug', 'description', 'is_active', 'display_order'];
protected $casts = ['is_active' => 'boolean', 'display_order' => 'integer'];
```

### Relationships

- `posts()` → `hasMany(BlogPost::class, 'blog_category_id')`
- `publishedPosts()` → `hasMany(BlogPost::class, 'blog_category_id')->published()

### Behavior

- Inactive categories (`is_active = false`): posts remain reachable by direct slug + direct category URL, but the category is excluded from the blog-index category-navigation list (edge case in spec).
- Deleting a category: cascade deletes set `blog_category_id` on posts to null — admin must reassign posts first (validation prevents deletion if posts exist).

---

## 3. Author Profile

Localized display metadata for a blog author (admin user). One-to-one with `users`.

### Migration: `author_profiles` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | `bigint` (PK) | no | auto | |
| `user_id` | `bigint` (FK→`users.id`) | no | — | Unique; 1:1 |
| `display_name` | `jsonb` | yes | — | Localized: `{ en, es, it }` |
| `bio` | `jsonb` | yes | — | Localized |
| `avatar_url` | `varchar(255)` | yes | — | R2 CDN URL |
| `created_at` | `timestamp` | no | now() | |
| `updated_at` | `timestamp` | no | now() | |

**Indexes**:
- `UNIQUE(user_id)`

**Foreign keys**:
- `user_id` → `users.id` (cascade on delete)

### Model: `AuthorProfile`

```php
protected $table = 'author_profiles';
protected $fillable = ['user_id', 'display_name', 'bio', 'avatar_url'];
protected $casts = ['display_name' => 'array', 'bio' => 'array', 'user_id' => 'integer'];
```

### Relationships

- `user()` → `belongsTo(User::class, 'user_id')`

### Behavior

- Optional: an admin user may have no `AuthorProfile` — in that case the byline falls back to the user's `name` field and no bio/avatar is rendered.
- Localized accessor reuses the `contentFor()` pattern.

---

## 4. Blog Post Tour (pivot)

Many-to-many link between blog posts and tours for discovery integration.

### Migration: `blog_post_tours` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | `bigint` (PK) | no | auto | |
| `blog_post_id` | `bigint` (FK→`blog_posts.id`) | no | — | |
| `tour_id` | `bigint` (FK→`tours.id`) | no | — | |
| `sort_order` | `integer` | no | `0` | Editorial ordering |

**Indexes**:
- `UNIQUE(blog_post_id, tour_id)` — prevents duplicate links
- `INDEX(blog_post_id, sort_order)` — ordered fetch for detail page
- `INDEX(tour_id)` — reverse lookup

**Foreign keys**:
- `blog_post_id` → `blog_posts.id` (cascade on delete)
- `tour_id` → `tours.id` (cascade on delete — if a tour is deleted, the link is removed)

### Behavior

- Admins may attach more than 6 related tours; only the first 6 eligible published tours (ordered by `sort_order`) are rendered publicly (clarification 2026-08-22).
- Only `Tour::published()` tours are surfaced publicly; archived tours are omitted silently (edge case in spec).

---

## 5. Admin Permission Flag Extension

### Migration: `add_manage_blog_to_admin_permissions`

The `admin_permissions` table (existing) stores a `flags` JSONB column. The migration adds
`manage_blog` to the allowed flags schema (if there's a DB constraint) or it's simply included in
the `AdminAuthorizationService::FLAGS` constant.

**Codebase evidence**: `AdminAuthorizationService::FLAGS` at `AdminAuthorizationService.php:22-32`
is a PHP constant — no migration needed unless the `flags` column has a CHECK constraint. The
migration is a no-op or adds a comment; the flag is enforced at the application layer.

### AdminAuthorizationService extension

```php
public const FLAGS = [
    'manage_tours', 'manage_partners', 'manage_bookings',
    'moderate_reviews', 'view_all_analytics', 'manage_users',
    'manage_settings', 'manage_cms', 'manage_blog', 'view_audit_log',
//                                                  ^^^^^^^^^^^^ added
];
```

---

## 6. Preview Token (ephemeral, not persisted)

A signed HMAC token issued from Filament, bound to `slug + expires_at`. Not stored in the database
— verification is stateless via HMAC recomputation.

### Token structure

```text
token = base64url(slug + "." + expires_at + "." + hmac_sha256(slug + "|" + expires_at, APP_PREVIEW_KEY))
```

- `slug`: the post's current slug (binding per clarification 2026-08-22)
- `expires_at`: Unix timestamp (issue time + 30 minutes)
- `APP_PREVIEW_KEY`: a `config('app.preview_key')` secret (added to `.env`)

### Verification

`GetBlogPostPreviewAction`:
1. Decode the token; split into `slug`, `expires_at`, `hmac`.
2. Recompute `hmac_sha256(requested_slug + "|" + expires_at, APP_PREVIEW_KEY)`.
3. Compare with `hash_equals()`. If mismatch → **403** ("Preview link expired").
4. If `expires_at < now()` → **403** ("Preview link expired").
5. If the recomputed slug ≠ requested `{slug}` → **403** (rebinding attack prevented).
6. Return the post regardless of status.

### Invalidation

- Token expires after 30 minutes (TTL).
- Changing a post's slug invalidates outstanding tokens (slug no longer matches).

---

## 7. Frontend View Models (TypeScript)

### BlogPostCard (list item)

```ts
interface BlogPostCard {
  id: number;
  slug: string;
  title: string;               // localized for the requested locale
  excerpt: string | null;      // localized
  cover_image_url: string | null;
  cover_image_blur: string | null;
  published_at: string;        // ISO 8601
  category: { slug: string; name: string };
  author: { display_name: string; avatar_url: string | null };
  is_featured: boolean;
  reading_time: number;        // minutes
  translation_warning: 'partial_translation' | null;
}
```

### BlogPostDetail

```ts
interface BlogPostDetail {
  id: number;
  slug: string;
  title: string;
  body: string;                // HTML
  excerpt: string | null;
  cover_image_url: string | null;
  cover_image_blur: string | null;
  published_at: string;
  updated_at: string;
  is_featured: boolean;
  reading_time: number;
  translation_warning: 'partial_translation' | null;
  category: { slug: string; name: string };
  author: {
    display_name: string;
    bio: string | null;
    avatar_url: string | null;
  };
  seo: SeoMetadata;            // { meta_title, meta_description, canonical_url, hreflang }
  related_tours: TourCard[];   // max 6, published only
  related_posts: BlogPostCard[]; // max 3, same-category + cross-category backfill
}
```

### BlogPostListResponse

```ts
interface BlogPostListResponse {
  data: BlogPostCard[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}
```

### BlogCategoryResponse

```ts
interface BlogCategoryResponse {
  data: {
    slug: string;
    name: string;
    description: string | null;
  };
  posts: BlogPostListResponse;
}
```

---

## Entity Relationship Summary

```text
  users (existing)
    │ 1:1
    ▼
  author_profiles
    │
  users ──── author_id ────┐
    │                      │
    │                      ▼
  blog_posts ──blog_category_id──→ blog_categories
    │
    │ M:N (blog_post_tours pivot)
    ▼
  tours (existing)
```

- `users` 1:N `blog_posts` (an admin authors many posts)
- `blog_categories` 1:N `blog_posts` (a category groups many posts)
- `blog_posts` M:N `tours` (via `blog_post_tours` with `sort_order`)
- `users` 1:1 `author_profiles` (optional; display metadata only)
- `governance_audit_logs` (existing) — `blog.*` action keys target `blog_posts` via morph map