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
  creation_id?: string;
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

/* ─── EGM-003 revision governance (Spec 019 partner contract) ────────────── */

/** Kind recorded on an immutable tour content revision. */
export type TourRevisionKind = 'legacy_baseline' | 'partner_edit' | 'admin_correction' | 'publication';

/** State of an immutable tour content revision. */
export type TourRevisionState = 'draft' | 'proposed' | 'approved' | 'rejected' | 'withdrawn' | 'superseded';

/** Context opening a tour change request. */
export type TourRevisionRequestContext = 'initial_review' | 'published_review' | 'correction';

/** State of an administrator change request. */
export type TourRevisionRequestState = 'open' | 'answered' | 'approved' | 'rejected' | 'closed' | 'superseded';

/** Wire field reference: a stored snapshot tier path plus an optional
 *  plain-text note (max 500 characters). Only `path` and `note` are allowed. */
export interface TourRevisionFieldRef {
  path: string;
  note?: string;
}

/** Bounded approved-revision summary returned on the owned tour GET. */
export interface ApprovedRevisionSummary {
  id: number;
  revision_number: number;
  kind: TourRevisionKind;
  state: 'approved';
  payload_hash: string;
  created_at: string;
}

/** Bounded active-candidate summary returned on the owned tour GET. */
export interface ActiveCandidateSummary {
  id: number;
  revision_number: number;
  kind: TourRevisionKind;
  state: TourRevisionState;
  payload_hash: string;
  base_revision_id: number;
  submitted_at?: string | null;
  change_summary?: string | null;
}

/** One open administrator change request in the owned governance summary. */
export interface TourChangeRequest {
  id: number;
  context: TourRevisionRequestContext;
  state: TourRevisionRequestState;
  reason: string;
  field_refs: TourRevisionFieldRef[];
  response_generation_epoch?: number | null;
  created_at: string;
}

/**
 * Additive `content_governance` object on `GET /api/partner/tours/{id}`.
 * Never `null` for an owned tour the platform protects: a published legacy
 * tour arrives with a lazily captured baseline, and a never-public tour
 * reports a real `generation_epoch` with a null `approved_revision`.
 */
export interface ContentGovernance {
  source_locale: ContentSourceLocale;
  generation_epoch: number;
  approved_revision: ApprovedRevisionSummary | null;
  active_candidate: ActiveCandidateSummary | null;
  latest_revision?: Pick<ActiveCandidateSummary, 'id' | 'revision_number' | 'kind' | 'state'> | null;
  open_requests: TourChangeRequest[];
}

/* Stored snapshot tiers (the full immutable revision payload). */
export interface RevisionSnapshotSource {
  title?: string | null;
  description?: string | null;
  highlights?: string[] | null;
  inclusions?: string[] | null;
  exclusions?: string[] | null;
  meeting_point?: string | null;
  cancellation_policy?: string | null;
  important_information?: string[] | null;
  itinerary?: TourItineraryDay[] | null;
}

export interface RevisionSnapshotPresentation {
  category_id?: number | null;
  slug?: string | null;
  location?: string | null;
  location_slug?: string | null;
  duration_minutes?: number | null;
  duration_label?: string | null;
  group_size_min?: number | null;
  group_size_max?: number | null;
  difficulty_level?: 'easy' | 'moderate' | 'challenging' | null;
  guide_languages?: string[] | null;
  cover_image_url?: string | null;
}

/** Authored option snapshot: ids in live order with text only. Commercial
 *  keys (price, capacity, schedule, guide codes) stay out of payloads. */
export interface RevisionSnapshotOption {
  id: number;
  title: string;
  pickup_instructions?: string | null;
}

export interface RevisionSnapshotMedia {
  url: string;
  is_cover: boolean;
  alt_text?: string | null;
  position?: number;
}

/** Backend revision snapshots store options as an ID-keyed object; callers may
 *  also encounter an ordered array of option snapshots. */
export type RevisionSnapshotOptionsWire =
  | RevisionSnapshotOption[]
  | Record<string, { id?: number; title: string; pickup_instructions?: string | null }>;

/** Full payload of an immutable revision, exposed only through the owned
 *  single-revision route. Internal source/content hashes never appear here. */
export interface RevisionPayload {
  source: RevisionSnapshotSource;
  presentation?: RevisionSnapshotPresentation | null;
  options?: RevisionSnapshotOptionsWire | null;
  media?: RevisionSnapshotMedia[] | null;
}

/** One owned revision including its full immutable payload. `payload_hash`
 *  is the only exposed CAS replacement token. */
export interface TourRevisionDetail {
  id: number;
  revision_number: number;
  kind: TourRevisionKind;
  state: TourRevisionState;
  base_revision_id: number | null;
  payload: RevisionPayload;
  payload_hash: string;
  created_at: string;
  submitted_at?: string | null;
  change_summary?: string | null;
  diff?: Record<string, unknown> | null;
  requests?: TourChangeRequest[];
}

/** Metadata returned by create/replace (201/200). */
export interface TourRevisionMutationData {
  id: number;
  revision_number: number;
  kind: TourRevisionKind;
  state: TourRevisionState;
  payload_hash: string;
  base_revision_id: number;
  created_at: string;
}

/** Governed content patch for create/replace. `translations` reuses the
 *  existing authored source form keyed by the tour's FIXED source locale;
 *  the stored snapshot keeps those fields under its `source` tier. Clients
 *  map the owned snapshot back into this write shape explicitly. The base and
 *  observed-hash identity fields are mandatory per endpoint (create needs the
 *  observed base; replacement also needs the observed payload hash CAS) and
 *  are supplied by the call sites. */
export interface TourRevisionPatchPayload {
  change_summary?: string;
  /** Observed approved base (mandatory on create and replacement calls). */
  base_revision_id?: number;
  /** Observed CAS token; mandatory on replacement calls. */
  observed_payload_hash?: string;
  translations?: { [K in ContentSourceLocale]?: EnglishSourceInput };
  options?: RevisionSnapshotOption[];
  media?: PartnerTourMediaInput[];
  presentation?: RevisionSnapshotPresentation;
}

/** Partner response to an open change request: a published-context response
 *  links the exact submitted candidate; an initial-context response echoes
 *  the observed generation epoch. */
export interface TourRevisionResponsePayload {
  revision_id?: number;
  observed_generation_epoch?: number;
  response_note?: string;
}

/** Resolved result of a partner revision HTTP call. Kept non-throwing so the
 *  documented machine error codes (REVISION_CONFLICT, REVISION_ALREADY_OPEN,
 *  REVISION_SUPERSEDED, REVISION_TERMINAL, REQUEST_NOT_OPEN, …) drive the
 *  fail-closed UI without losing status codes. */
export interface PartnerApiResult<T> {
  ok: boolean;
  status: number;
  data?: T;
  message?: string;
  errorCode?: string;
  errors?: Record<string, string[]>;
  revisionId?: number;
}


/**
 * Concrete owned draft snapshot as serialized by the partner draft
 * endpoints (`POST .../drafts/save`, `GET .../drafts/latest`). The payload
 * is opaque: arbitrary autosave content validated only on materialization,
 * never trusted as canonical source without restoration/normalization.
 */
export interface TourDraftSnapshot {
  id: number;
  draft_id?: number;
  tour_id: number | null;
  partner_id?: number;
  creation_id?: string | null;
  concurrency_token?: string | null;
  current_step?: string | null;
  source_locale?: ContentSourceLocale | null;
  payload: unknown;
  status?: string;
  auto_saved_at?: string | null;
  created_at?: string;
  updated_at?: string | null;
}

export interface TourDraftConflictServerDraft {
  id: number;
  creation_id?: string | null;
  tour_id?: number | null;
  concurrency_token: string | null;
  updated_at?: string | null;
  current_step?: string | null;
  source_locale?: ContentSourceLocale | null;
  payload: unknown;
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

export interface TieredPricingTier {
  id: number;
  name: string;
  min_participants: number;
  max_participants: number | null;
  price_amount: number;
  currency: string;
  formatted: string;
}

export interface TieredPricingInfo {
  mode: 'per_person';
  tiers: TieredPricingTier[];
}

export interface PricingInfo {
  base_price: {
    amount: number;
    currency: string;
    formatted: string;
  };
  tiered_pricing: TieredPricingInfo | null;
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

export interface PartnerAvailabilityRule {
  id: number;
  rule_type: 'recurring' | 'specific_date' | string;
  days_of_week: number[] | null;
  start_time: string;
  start_date: string;
  end_date: string | null;
  capacity: number;
}

export interface PartnerAvailabilityException {
  id: number;
  exception_type: 'block' | 'capacity_override' | 'price_multiplier' | string;
  date: string;
  start_time: string | null;
  capacity: number | null;
  price_multiplier: string;
  note: string | null;
}

export interface PartnerAvailabilityData {
  rules: PartnerAvailabilityRule[];
  exceptions: PartnerAvailabilityException[];
}

export interface PartnerDepartureOptionSummary {
  id: number;
  title: string;
  start_time: string | null;
  group_size_max: number | null;
  effective_capacity: number;
}

export interface PartnerTourDeparture {
  id: number;
  tour_id: number;
  tour_option_id: number;
  option_title: string | null;
  starts_at: string;
  timezone: string;
  local_date: string;
  local_time: string;
  capacity: number;
  reserved_seats: number;
  available_spots: number;
  booking_cutoff_hours: number;
  status: 'active' | 'cancelled' | 'completed';
  is_bookable: boolean;
}

export interface PartnerDeparturesResponse {
  data: PartnerTourDeparture[];
  meta: {
    total: number;
    max_horizon_days: number;
    options: PartnerDepartureOptionSummary[];
  };
}

export interface PublicTourDeparturePricing {
  mode: string;
  tier_id: number | null;
  tier_name: string | null;
  min_participants: number;
  max_participants: number | null;
  unit_price_cents: number;
  total_price_cents: number;
  currency: string;
}

export interface PublicTourDeparture {
  id: number;
  tour_id: number;
  tour_option_id: number;
  starts_at: string;
  timezone: string;
  local_date: string;
  local_time: string;
  capacity: number;
  reserved_spots: number;
  available_spots: number;
  booking_cutoff_hours: number;
  is_bookable: boolean;
  pricing: PublicTourDeparturePricing;
}

export interface PartnerPricingTierRecord {
  id: number;
  tour_id: number;
  tour_option_id: number | null;
  name: string;
  price: string;
  price_amount: number;
  currency: string;
  min_participants: number;
  max_participants: number | null;
  sort_order: number;
}

export interface PartnerPricingOptionSummary {
  id: number;
  title: string;
  effective_capacity: number;
  price_amount: number;
  currency: string;
}

export interface PartnerPricingResponse {
  data: PartnerPricingTierRecord[];
  meta?: {
    mode: 'per_person';
    options: PartnerPricingOptionSummary[];
  };
}

