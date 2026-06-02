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

export interface TourDetail extends TourCard {
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
  featured_tours: TourCard[];
  popular_categories: Category[];
  featured_destinations: Destination[];
  meta: {
    seo: {
      meta_title: string;
      meta_description: string;
    };
  };
}

// ── Contract-aligned types (contracts/) ──────────────────────

export interface ContractPrice {
  amount: number;
  currency: string;
}

export interface ContractTourCard {
  id: number;
  slug: string;
  title: string;
  cover_image: string;
  cover_image_blur?: string;
  price_per_person: ContractPrice;
  average_rating: number;
  total_reviews: number;
  location: string;
  duration: string;
  category: { slug: string; name: string };
}

export interface ContractTourImage {
  url: string;
  alt: string;
  blur_data_url?: string;
}

export interface ContractReview {
  id: number;
  author: string;
  rating: number;
  comment: string;
  created_at: string;
}

export interface ContractAvailabilitySlot {
  date: string;
  time_slot: string;
  price_per_person: ContractPrice;
  remaining_capacity: number;
}

export interface ContractTourDetail extends ContractTourCard {
  description: string;
  highlights: string[];
  inclusions: string[];
  exclusions: string[];
  meeting_point: string;
  images: ContractTourImage[];
  destination: { slug: string; name: string };
  reviews: ContractReview[];
  availability: ContractAvailabilitySlot[];
}

export interface ContractPaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface ContractBookingRequest {
  tour_id: number;
  date: string;
  time_slot: string;
  participants: number;
  guest_name: string;
  guest_email: string;
  guest_phone?: string;
  special_requests?: string;
  locale: string;
}

export interface ContractBookingResponse {
  booking_id: number;
  payment_intent?: {
    client_secret: string;
    amount: number;
    currency: string;
  };
  tour_title: string;
  idempotency_key: string;
}

export interface ContractBookingConfirmation {
  reference: string;
  tour_title: string;
  date: string;
  time_slot: string;
  participants: number;
  amount: ContractPrice;
  guest_name: string;
  guest_email: string;
  meeting_point: string;
  created_at: string;
}

export interface ContractAvailabilityCheck {
  available: boolean;
  remaining_capacity: number;
  price_per_person?: ContractPrice;
  total_price?: ContractPrice;
  message?: string;
}

export interface ContractAuthUser {
  id: number;
  name: string;
  email: string;
  phone?: string;
}
