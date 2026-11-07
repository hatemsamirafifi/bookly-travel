# Data Model: Homepage and Discovery (Spec 021)

**Feature**: Homepage and Discovery Redesign  
**Date**: 2026-10-29  
**Status**: Aligned with actual Bookly PostgreSQL schema, Laravel domain actions, and TypeScript interfaces.

---

## 1. Frontend TypeScript Interfaces (`frontend/src/lib/api/types.ts`)

### `HomepageData`
The envelope returned by `GET /api/public/homepage` and consumed by `page.tsx`:
```typescript
export interface HomepageData {
  data: {
    featured_tours: TourCard[];
    popular_categories: Category[];
    featured_destinations: Destination[];
    curated_rails: CuratedRail[];
  };
  meta: {
    seo: {
      meta_title: string;
      meta_description: string;
    };
  };
}

export interface CuratedRail {
  id: string;
  slug: string;
  title: string;
  tours: TourCard[];
}
```

### `TourCard`
The standardized atom representing a tour in discovery rails, category showcases, and search grids:
```typescript
export interface TourCard {
  id: number;
  slug: string;
  title: string;
  location: string;
  category: string;
  duration_label: string;
  price: {
    amount: number;         // Integer cents (e.g., 4900 = $49.00)
    currency: string;       // ISO-4217 code (e.g., "USD", "EUR")
    formatted: string;      // Formatted with currency symbol per locale (e.g., "$49")
  };
  rating: {
    average: number;        // Float (e.g., 4.85)
    count: number;          // Total approved reviews
  };
  cover_image_url: string;  // Absolute URL
  group_size: {
    min: number;
    max: number;
  };
  next_available_date: string | null;
  badge?: 'featured' | 'best-seller' | 'new' | null;
}
```

### `Category` & `Destination`
```typescript
export interface Category {
  slug: string;
  name: string;
  description?: string | null;
  image_url?: string | null;
  tour_count: number;
}

export interface Destination {
  slug: string;
  name: string;
  country: string;
  image_url?: string | null;
  tour_count: number;
}
```

### `SearchFilterState` (Active Query & Staged Drawer State)
```typescript
export interface SearchFilterState {
  q?: string;
  category?: string;
  destination?: string;
  minPrice?: number;
  maxPrice?: number;
  rating?: number;
  duration?: 'under_2h' | '2h_to_4h' | '4h_to_8h' | 'full_day' | 'multi_day';
  sortBy?: 'popularity' | 'price_asc' | 'price_desc' | 'rating';
  page?: number;
}
```

### `GuestWishlistStorage` (Browser `localStorage`)
Key: `bookly_guest_wishlist`
```typescript
export interface GuestWishlistStorage {
  version: 1;
  tour_ids: number[];
  updated_at: string; // ISO-8601 UTC
}
```

---

## 2. Backend Data Models & Persistence (Laravel 11 / PostgreSQL)

### Database Entities & Relationships
1. **`tours` Table**:
   - `id` (bigint PK)
   - `operator_id` (FK → users)
   - `category_id` (FK → categories)
   - `destination_id` (FK → destinations, nullable)
   - `is_featured` (boolean, indexed)
   - `status` (`published`, `draft`, etc.)
   - `price_cents` (integer)
   - `currency` (varchar 3)
   - `rating_cache` (decimal 3,2)
   - `reviews_count_cache` (integer)
2. **`tour_translations` Table**:
   - `id` (bigint PK), `tour_id` (FK), `locale` (varchar 5), `title`, `slug`, `location_label`
3. **`categories` Table**:
   - `id` (bigint PK), `slug`, `name`, `description`, `image_url`
4. **`destinations` Table**:
   - `id` (bigint PK), `slug`, `name`, `country`, `image_url`
5. **`wishlists` Table**:
   - `id` (bigint PK), `user_id` (FK → users), `tour_id` (FK → tours), timestamps
   - Unique constraint: `(user_id, tour_id)`

---

## 3. Caching & State Lifecycle

| Layer | Key / Path | Invalidation / TTL | Purpose |
|:------|:-----------|:-------------------|:--------|
| **Redis Cache** | `homepage.{locale}` | 600 seconds (10 mins) or invalidated on tour publication / feature toggle | Fast, cached response for `GET /api/public/homepage` |
| **Next.js SSR** | Route `/api/public/homepage` | `revalidate: 300` (5 mins) | ISR deduplication between server-side page renders |
| **Client Storage** | `bookly_guest_wishlist` | Persists until merged on login or explicitly cleared on logout | Offline/guest bookmarking with zero auth barrier |
