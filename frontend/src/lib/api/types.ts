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
  badge?: 'featured' | 'best-seller' | 'new' | null;
}

export interface TourDetail extends Pick<TourCard, 'id' | 'slug' | 'title' | 'location' | 'rating' | 'group_size'> {
  // The detail response has a different shape from search cards.
  category: { slug: string; name: string };
  description: string;
  content_locale: 'en' | 'es' | 'it';
  highlights: string[];
  inclusions: string[];
  exclusions: string[];
  important_information?: string[];
  meeting_point: string;
  cancellation_policy: string;
  duration: {
    minutes: number;
    label: string;
  };
  difficulty_level?: 'easy' | 'moderate' | 'challenging' | null;
  languages: string[];
  guide_languages: string[];
  itinerary: TourItineraryDay[];
  itinerary_locale: 'en' | 'es' | 'it';
  images: TourImage[];
  pricing: PricingInfo;
  availability: AvailabilityInfo;
  reviews: ReviewsInfo;
  operator?: PublicOperatorSummary | null;
  related_tours?: TourCard[];
  seo: SeoMetadata;
  translation_warning?: 'partial_translation';
  translation_status?: 'source' | 'ready' | 'pending' | 'stale' | 'failed';
  /**
   * Authored bookable options (additive contract). Absent or empty means the
   * tour books from its tour-level pricing/availability as before. Each
   * option carries its own localized content language, spoken guide
   * languages, pricing and availability surface.
   */
  options?: TourOption[];
}

/** One bookable option of a public tour detail (additive; see TourDetail.options). */
export interface TourOption {
  id: number;
  /** Authored option title in the option's own content language. */
  title: string;
  pickup_instructions: string | null;
  /** Actual language of title/pickup_instructions; may differ from the page. */
  content_locale: 'en' | 'es' | 'it';
  /** Spoken/live-guide codes; independent of the content locale. */
  guide_languages: string[];
  start_time: string | null;
  pricing: PricingInfo;
  availability: AvailabilityInfo;
  group_size: { min: number; max: number };
}

/** Booked option snapshot as serialized in booking responses and details. */
export interface BookingOptionSnapshot {
  id: number | null;
  title: string;
  pickup_instructions: string | null;
  content_locale: 'en' | 'es' | 'it' | null;
}

export interface TourImage {
  url: string;
  is_cover: boolean;
  alt: string;
  /** Actual language of the specific alt text: 'en' for partner-supplied
   *  English alt, otherwise the selected content locale. */
  alt_locale?: 'en' | 'es' | 'it' | null;
}

/** Canonical English source patch (Spec 019 partner contract). Explicit
 *  nested `translations.en` keys win; shorthand fills absent nested keys;
 *  omitted keys preserve values; null clears nullable text; [] clears lists.
 *  Title is a non-null string whenever supplied. */
export interface EnglishSourceInput {
  title?: string;
  description?: string | null;
  highlights?: string[] | null;
  inclusions?: string[] | null;
  exclusions?: string[] | null;
  meeting_point?: string | null;
  cancellation_policy?: string | null;
  itinerary?: TourItineraryDay[] | null;
  important_information?: string[] | null;
}

export interface PartnerTourMediaInput {
  url: string;
  is_cover?: boolean;
  alt_text?: string | null;
}

export interface PartnerPricingTierInput {
  name: string;
  price: number;
  currency: string;
  min_participants: number;
  max_participants: number;
}

export interface PartnerAvailabilityRuleInput {
  id?: string;
  rule_type: 'daily' | 'weekly' | 'monthly';
  days_of_week: number[];
  start_time: string;
  start_date: string;
  end_date?: string;
  capacity: number;
}

export interface PartnerAvailabilityExceptionInput {
  id?: string;
  exception_type: 'blackout' | 'specific';
  date: string;
  start_time: string;
  capacity: number;
  price_multiplier: string;
  note?: string;
}

/**
 * One authored booking option (additive write contract). `id` is the
 * persistent server row id and MUST be preserved when editing an existing
 * option; rows without `id` are created. `price_amount` is integer minor
 * units (cents) in the tour currency; partner UI shows major units.
 * Omitting `options` preserves existing rows; sending `[]` retires them.
 */
export interface PartnerBookingOptionInput {
  id?: number;
  title: string;
  pickup_instructions?: string | null;
  guide_languages: string[];
  price_amount: number;
  group_size_max?: number | null;
  start_time?: string | null;
  weekdays?: number[] | null;
}

/** Content locale an authored option is written in. */
export type ContentSourceLocale = 'en' | 'es' | 'it';

export interface PartnerTourWritePayload {
  title?: string;
  description?: string | null;
  highlights?: string[] | null;
  inclusions?: string[] | null;
  exclusions?: string[] | null;
  meeting_point?: string | null;
  cancellation_policy?: string | null;
  itinerary?: TourItineraryDay[] | null;
  important_information?: string[] | null;
  /** Language the authored source content is written in (en/es/it). Sent on
   *  creation only; immutable afterwards and never resubmitted on updates. */
  source_locale?: ContentSourceLocale;
  /** Authored source content keyed by its own language: the selected
   *  writing language on create (plus later edits of that same language);
   *  the other two locales are generated server-side and never authored. */
  translations?: { en?: EnglishSourceInput; es?: EnglishSourceInput; it?: EnglishSourceInput };
  /** Authored bookable options. Omitted preserves existing rows; `[]`
   *  retires them; absent rows are created / updated by persistent id. */
  options?: PartnerBookingOptionInput[];
  /** Nullable: older tours without difficulty stay null; never invented. */
  difficulty_level?: 'easy' | 'moderate' | 'challenging' | null;
  /** Spoken/live-guide codes, independent of EN/ES/IT content locales. */
  guide_languages?: string[];
  /** Compatibility alias for guide_languages; guide_languages wins. */
  languages?: string[];
  category?: string;
  category_id?: number;
  destination?: string;
  location?: string;
  duration_value?: number;
  duration_unit?: 'hour' | 'day';
  group_size_min?: number;
  group_size_max?: number;
  cover_image_url?: string | null;
  media?: PartnerTourMediaInput[];
  price_from?: number | null;
  currency?: string | null;
  pricing_tiers?: PartnerPricingTierInput[];
  availability_rules?: PartnerAvailabilityRuleInput[];
  availability_exceptions?: PartnerAvailabilityExceptionInput[];
}

/** Sanitized owned per-locale readiness (partner detail only; `source` is
 *  EN-only so owned es/it keys never carry it). Hashes and provider errors
 *  are never serialized. */
export type OwnedTranslationStatus = 'pending' | 'ready' | 'stale' | 'failed';

/**
 * Concrete owned draft snapshot as serialized by the partner draft
 * endpoints (`POST .../drafts/save`, `GET .../drafts/latest`). The payload
 * is opaque: arbitrary autosave content validated only on materialization,
 * never trusted as canonical source without restoration/normalization.
 */
export interface TourDraftSnapshot {
  id: number;
  tour_id: number | null;
  partner_id: number;
  payload: unknown;
  status: string;
  auto_saved_at?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface TourItineraryDay {
  day: number;
  title: string;
  description?: string | null;
  stops?: Array<{
    title: string;
    description?: string | null;
    duration_minutes?: number | null;
  }>;
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
  is_unavailable: boolean;
}

export interface ReviewsInfo {
  average_rating: number;
  count: number;
  distribution: Record<string, number>;
}

export interface PublicOperatorSummary {
  name: string;
  description: string | null;
  logo_url: string | null;
  /** Additive: true only for approved + active eligible profiles. */
  verified?: boolean;
  tour_count: number;
  review_count: number;
  average_rating: number | null;
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
  /** Major currency units as typed by the user (e.g. euros). searchTours()
   * converts these to the API contract's minor units (cents) before sending. */
  price_min?: number;
  /** Major currency units as typed by the user (e.g. euros). searchTours()
   * converts these to the API contract's minor units (cents) before sending. */
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

export interface CuratedRail {
  id: string;
  slug: string;
  title: string;
  tours: TourCard[];
}

export interface HomepageData {
  data: {
    featured_tours: TourCard[];
    popular_categories: Category[];
    featured_destinations: Destination[];
    curated_rails?: CuratedRail[];
  };
  meta: {
    seo: {
      meta_title: string;
      meta_description: string;
    };
  };
}

export interface BlogCategorySummary {
  slug: string;
  name: string;
  description?: string | null;
  image_url?: string | null;
}

export interface BlogAuthorSummary {
  display_name: string;
  avatar_url?: string | null;
  bio?: string | null;
}

export interface BlogPostCard {
  id: number;
  slug: string;
  title: string;
  excerpt: string;
  cover_image_url?: string | null;
  cover_image_blur?: string | null;
  published_at: string | null;
  is_featured: boolean;
  reading_time: number;
  translation_warning: 'partial_translation' | null;
  category: BlogCategorySummary | null;
  author: BlogAuthorSummary;
}

export interface BlogPostDetail extends BlogPostCard {
  body: string;
  updated_at: string | null;
  seo: SeoMetadata;
  related_tours: TourCard[];
  related_posts: BlogPostCard[];
  is_preview?: boolean;
  status?: string;
}

export interface BlogListResponse {
  data: BlogPostCard[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface BlogDetailResponse {
  data: BlogPostDetail;
}

export interface BlogCategoryResponse {
  data: {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    image_url?: string | null;
    posts: BlogPostCard[];
  };
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

/**
 * Published CMS static page as served by `GET /api/public/pages/{slug}`
 * (Admin 2.0 Phase G). Meta fields are omitted from the payload when no
 * value is stored; `translation_warning` flags missing-locale fallbacks.
 */
export interface PublicStaticPage {
  slug: string;
  locale: string;
  title: string;
  body: string;
  published_at?: string | null;
  meta_title?: string;
  meta_description?: string;
  canonical_url?: string;
  og_image?: string;
  translation_warning?: string;
}

