export interface TourCard {
  id: number;
  slug: string;
  title: string;
  location: string;
  category: string;
  duration_label: string;
  price: {
    amount: number;
    currency: string;
    formatted: string;
  };
  rating: {
    average: number;
    count: number;
  };
  cover_image_url: string;
  group_size: {
    min: number;
    max: number;
  };
  next_available_date: string | null;
}

export interface TourDetail extends Omit<TourCard, 'category'> {
  // The detail endpoint returns the category as an object
  // (tour-detail-api.md:42), overriding the string name on TourCard
  // which is what the list/search transformer returns (search-api.md:61).
  category: { slug: string; name: string };
  description: string;
  highlights: string[];
  inclusions: string[];
  exclusions: string[];
  meeting_point: string;
  cancellation_policy: string;
  duration: {
    minutes: number;
    label: string;
  };
  languages: string[];
  images: TourImage[];
  pricing: PricingInfo;
  availability: AvailabilityInfo;
  reviews: ReviewsInfo;
  seo: SeoMetadata;
  translation_warning?: 'partial_translation';
}

export interface TourImage {
  url: string;
  is_cover: boolean;
  alt: string;
}

export interface PricingInfo {
  base_price: {
    amount: number;
    currency: string;
    formatted: string;
  };
  tiered_pricing: null;
}

export interface AvailabilityInfo {
  next_available_date: string | null;
  available_dates: string[];
  // Backend flag (tour-detail-api.md:115-116): a published tour reached by
  // direct URL that fails the bookable invariant (no valid pricing or no
  // upcoming availability) is served with is_unavailable=true so the UI shows
  // "Currently Unavailable" rather than a Book Now CTA.
  is_unavailable?: boolean;
}

export interface ReviewsInfo {
  average_rating: number;
  count: number;
  distribution: Record<string, number>;
}

export interface SeoMetadata {
  meta_title: string;
  meta_description: string;
  canonical_url: string;
  hreflang: Record<string, string>;
}

export interface Category {
  slug: string;
  name: string;
  description?: string;
  image_url?: string;
  tour_count: number;
}

export interface Destination {
  slug: string;
  name: string;
  country: string;
  image_url?: string;
  tour_count: number;
  is_featured?: boolean;
}

export interface SearchParams {
  q?: string;
  locale: string;
  category?: string;
  location?: string;
  price_min?: number;
  price_max?: number;
  duration?: string;
  date?: string;
  sort?: 'relevance' | 'price_asc' | 'price_desc' | 'rating' | 'newest';
  page?: number;
}

export interface SearchResponse {
  data: TourCard[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  filters: {
    categories: { slug: string; name: string; count: number }[];
    locations: { slug: string; name: string; count: number }[];
    price_range: { min: number; max: number };
    durations: { value: string; label: string; count: number }[];
  };
}

export interface HomepageData {
  data: {
    featured_tours: TourCard[];
    popular_categories: Category[];
    featured_destinations: Destination[];
  };
  meta: {
    seo: {
      meta_title: string;
      meta_description: string;
    };
  };
}
