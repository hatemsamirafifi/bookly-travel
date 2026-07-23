# Quickstart: Public Search & Discovery

**Feature**: 006-public-search-discovery
**Date**: 2026-05-06

## Prerequisites

- Docker and Docker Compose running
- Laravel backend set up (specs 001–005 implemented)
- Next.js frontend set up
- Node.js 20+ and Composer installed

## 1. Start Meilisearch

Add Meilisearch to `docker-compose.yml` if not already present:

```yaml
meilisearch:
  image: getmeili/meilisearch:v1.10
  ports:
    - "7700:7700"
  environment:
    MEILI_MASTER_KEY: ${MEILI_MASTER_KEY}
    MEILI_ENV: development
  volumes:
    - meilisearch_data:/meili_data
```

```bash
docker compose up -d meilisearch
```

Add to `.env`:

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=${MEILI_MASTER_KEY}
```

## 2. Configure Scout & Meilisearch Index

Install Scout if not already:

```bash
cd backend
composer require laravel/scout
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

Configure the Tour model for search:

```php
// app/Models/Tour.php
use Laravel\Scout\Searchable;

class Tour extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title_en' => $this->translate('en')?->title,
            'title_es' => $this->translate('es')?->title,
            'title_it' => $this->translate('it')?->title,
            // ... other fields per data-model.md
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === 'published'
            && $this->hasValidPricing()
            && $this->hasUpcomingAvailability();
    }

    // Note: Tour model observer sets published_at = now() on first
    // transition to 'published' status. See data-model.md for 404/410 logic.
}
```

## 3. Configure Meilisearch Index Settings

Create a console command or migration to set index configuration:

```php
// In a setup command or service provider
$client = new \Meilisearch\Client(
    config('scout.meilisearch.host'),
    config('scout.meilisearch.key')
);

$client->index('tours')->updateSettings([
    'searchableAttributes' => [
        'title_en', 'title_es', 'title_it',
        'description_en', 'description_es', 'description_it',
        'location', 'category_name',
        'highlights_en', 'highlights_es', 'highlights_it'
    ],
    'filterableAttributes' => [
        'status', 'category_slug', 'location_slug',
        'price_amount', 'duration_minutes', 'available_dates'
    ],
    'sortableAttributes' => [
        'price_amount', 'average_rating', 'created_at', 'review_count'
    ],
    'rankingRules' => [
        'words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'
    ]
]);
```

## 4. Index Existing Tours

```bash
cd backend
php artisan scout:import "App\Models\Tour"
```

## 5. Create Search Routes

Add to `routes/api.php`:

```php
Route::prefix('public')->group(function () {
    Route::get('/search/tours', [SearchController::class, 'search']);
    Route::get('/tours/{slug}', [TourDetailController::class, 'show']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}/tours', [CategoryController::class, 'tours']);
    Route::get('/destinations', [DestinationController::class, 'index']);
    Route::get('/destinations/{slug}/tours', [DestinationController::class, 'tours']);
    Route::get('/homepage', [HomepageController::class, 'index']);
    Route::get('/sitemap.xml', [SitemapController::class, 'index']);
});
```

## 6. Frontend Setup — Search Page

```bash
cd frontend
mkdir -p src/app/\[locale\]/search
mkdir -p src/components/search
mkdir -p src/lib/api
```

Create API client (`src/lib/api/search.ts`):

```typescript
export async function searchTours(params: SearchParams) {
  const searchParams = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== '') searchParams.set(k, String(v));
  });
  const res = await fetch(`${API_URL}/api/public/search/tours?${searchParams}`);
  if (!res.ok) throw new Error('Search failed');
  return res.json();
}
```

## 7. Verify End-to-End Flow

```bash
# 1. Seed test data
cd backend
php artisan db:seed --class=TourSearchTestSeeder

# 2. Index tours
php artisan scout:import "App\Models\Tour"

# 3. Test API directly
curl "http://localhost:8000/api/public/search/tours?locale=en&q=wine"

# 4. Start frontend and test
cd frontend
npm run dev
# Visit http://localhost:3000/en/search?q=wine
```

## 8. Run Tests

```bash
# Backend
cd backend
php artisan test --filter=Search

# Frontend
cd frontend
npx playwright test search.spec.ts
```

## Key Files Checklist

- [ ] Meilisearch running in Docker
- [ ] `.env` configured with `SCOUT_DRIVER` and `MEILISEARCH_*`
- [ ] `Tour` model uses `Searchable` trait with `toSearchableArray()` and `shouldBeSearchable()`
- [ ] Search routes registered in `routes/api.php`
- [ ] `SearchController`, `TourDetailController`, `CategoryController`, `SitemapController` created
- [ ] Rate limiting middleware applied to search routes
- [ ] Frontend `[locale]` routing with `/search`, `/tours/[slug]` pages
- [ ] Search components (`SearchBar`, `SearchResults`, `TourCard`, `FilterPanel`) created
- [ ] i18n translation files (`en.json`, `es.json`, `it.json`) populated
- [ ] Sitemap and robots.txt accessible
