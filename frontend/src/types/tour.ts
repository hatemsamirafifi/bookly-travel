/**
 * Tour domain types for the Partner Dashboard.
 * Aligned with the data model in specs/012-partner-dashboard/data-model.md
 * and the existing partner types in @/types/partner.
 */

import type {
  Tour as PartnerTour,
  TourDraft as PartnerTourDraft,
  TourMedia as PartnerTourMedia,
  PricingTier as PartnerPricingTier,
  AvailabilityRule as PartnerAvailabilityRule,
  AvailabilityException as PartnerAvailabilityException,
  TourStatus as PartnerTourStatus,
  PaginatedPartnerResponse,
} from '@/types/partner';

// Re-export the canonical types from partner.ts so consumers have a single import point
export type Tour = PartnerTour;
export type TourDraft = PartnerTourDraft;
export type TourMedia = PartnerTourMedia;
export type PricingTier = PartnerPricingTier;
export type AvailabilityRule = PartnerAvailabilityRule;
export type AvailabilityException = PartnerAvailabilityException;
export type TourStatus = PartnerTourStatus;

/* ─── Wizard-specific types ─────────────────────────────────────────────── */

/** Steps in the multi-step tour creation wizard */
export type WizardStep = 'details' | 'media' | 'pricing' | 'availability' | 'review';

export const WIZARD_STEPS: WizardStep[] = ['details', 'media', 'pricing', 'availability', 'review'];

export const WIZARD_STEP_LABELS: Record<WizardStep, string> = {
  details: 'Basic Info',
  media: 'Media',
  pricing: 'Pricing',
  availability: 'Availability',
  review: 'Review',
};

/** Duration unit options */
export type DurationUnit = 'hour' | 'day';

/** Difficulty level options */
export type DifficultyLevel = 'easy' | 'moderate' | 'challenging';

/** Form data for the tour creation/edit wizard */
export interface TourFormData {
  title: string;
  description: string;
  category: string;
  destination: string;
  duration_value: string;
  duration_unit: DurationUnit;
  difficulty_level: DifficultyLevel;
  itinerary: string;
  inclusions: string;
  meeting_point: string;
  languages: string[];
  cancellation_policy: string;
  media: TourMedia[];
  pricing_tiers: PricingTierFormInput[];
  availability_rules: AvailabilityRuleFormInput[];
  availability_exceptions: AvailabilityExceptionFormInput[];
  min_participants: number;
  max_participants: number;
}

/** New pricing tier input (no id yet) */
export interface PricingTierFormInput {
  id: string; // client-side temp id (crypto.randomUUID())
  name: string;
  price: string; // string for form input, converted to number on submit
  currency: string;
  min_participants: number;
  max_participants: number;
}

/** New availability rule input */
export interface AvailabilityRuleFormInput {
  id: string; // client-side temp id
  rule_type: 'daily' | 'weekly' | 'monthly';
  days_of_week: number[]; // 0 = Sunday, 6 = Saturday
  start_time: string; // HH:MM
  start_date: string; // YYYY-MM-DD
  end_date: string; // YYYY-MM-DD or empty for indefinite
  capacity: number;
}

/** New availability exception input */
export interface AvailabilityExceptionFormInput {
  id: string; // client-side temp id
  exception_type: 'blackout' | 'specific';
  date: string; // YYYY-MM-DD
  start_time: string; // HH:MM
  capacity: number;
  price_multiplier: string; // string for form input, e.g. "1.20"
  note: string;
}

/* ─── Tour list types ───────────────────────────────────────────────────── */

export interface TourListFilters {
  status?: TourStatus;
  search?: string;
  page?: number;
  per_page?: number;
}

export type TourListResponse = PaginatedPartnerResponse<Tour>;

/* ─── Upload types ──────────────────────────────────────────────────────── */

export interface ImageUploadState {
  file: File;
  preview: string;
  progress: number; // 0-100
  status: 'idle' | 'uploading' | 'done' | 'error';
  publicUrl?: string;
  error?: string;
}

/* ─── Initial form data ─────────────────────────────────────────────────── */

export const INITIAL_TOUR_FORM_DATA: TourFormData = {
  title: '',
  description: '',
  category: '',
  destination: '',
  duration_value: '',
  duration_unit: 'hour',
  difficulty_level: 'easy',
  itinerary: '',
  inclusions: '',
  meeting_point: '',
  languages: [],
  cancellation_policy: '',
  media: [],
  pricing_tiers: [],
  availability_rules: [],
  availability_exceptions: [],
  min_participants: 1,
  max_participants: 20,
};