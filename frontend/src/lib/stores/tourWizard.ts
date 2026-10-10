import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import type { TourItineraryDay } from '@/lib/api/types';
import type {
  WizardStep,
  TourFormData,
  PricingTierFormInput,
  AvailabilityRuleFormInput,
  AvailabilityExceptionFormInput,
  TourMedia,
} from '@/types/tour';

// Inline the initial data to avoid circular issues at module init
const INITIAL_FORM: TourFormData = {
  title: '',
  description: '',
  category: '',
  destination: '',
  duration_value: '',
  duration_unit: 'hour',
  difficulty_level: 'easy',
  itinerary: [],
  highlights: [],
  inclusions: [],
  exclusions: [],
  important_information: [],
  meeting_point: '',
  languages: [],
  cancellation_policy: '',
  media: [],
  pricing_tiers: [],
  availability_rules: [],
  availability_exceptions: [],
  group_size_min: 1,
  group_size_max: 20,
};

const STRING_ARRAY_KEYS = [
  'highlights',
  'inclusions',
  'exclusions',
  'important_information',
  'languages',
] as const;

/** Split recognized older free-text source content (comma- and/or
 *  newline-separated) into a bounded ordered list, preserving content. */
function textToList(value: string): string[] {
  return value
    .split(/[,\n]/)
    .map((item) => item.trim())
    .filter((item) => item !== '')
    .slice(0, 30);
}

function toStringArray(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value.filter((item): item is string => typeof item === 'string');
  }
  if (typeof value === 'string') {
    return textToList(value);
  }
  return [];
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

type NormalizedStop = NonNullable<TourItineraryDay['stops']>[number];

/** Guard a raw stop entry into a renderable row without fabricating facts:
 *  non-object entries are dropped; object entries keep their text with
 *  blank-title rows left for canonical validation to flag on submit. */
function normalizeStop(entry: unknown): NormalizedStop | null {
  if (!isRecord(entry)) {
    return null;
  }
  const rawDuration = entry.duration_minutes;
  return {
    title: typeof entry.title === 'string' ? entry.title : '',
    description: typeof entry.description === 'string' ? entry.description : null,
    duration_minutes:
      typeof rawDuration === 'number' && Number.isFinite(rawDuration) ? rawDuration : null,
  };
}

function normalizeDay(entry: unknown, index: number): TourItineraryDay | null {
  if (typeof entry === 'string') {
    return { day: index + 1, title: entry, description: null, stops: [] };
  }
  if (!isRecord(entry)) {
    return null;
  }
  const rawStops = entry.stops;
  const stops = Array.isArray(rawStops)
    ? rawStops
        .map((stop) => normalizeStop(stop))
        .filter((stop): stop is NormalizedStop => stop !== null)
    : [];
  return {
    day: typeof entry.day === 'number' && Number.isFinite(entry.day) ? entry.day : index + 1,
    title: typeof entry.title === 'string' ? entry.title : '',
    description: typeof entry.description === 'string' ? entry.description : null,
    stops,
  };
}

/**
 * Normalize a possibly-older persisted wizard snapshot before canonical
 * validation (Spec 019 T018). Accepts unknown input (parsed storage JSON):
 * missing array fields default to `[]`, older free-text source content is
 * preserved as bounded lists, legacy string-array itineraries convert to
 * structured days, day objects gain guarded stops, unrenderable day entries
 * are dropped, and a missing/null/invalid older difficulty stays absent
 * (null) instead of inheriting the new-tour `easy` default. Stored opaque
 * server snapshots are never mutated here; only the restored form copy.
 */
export function normalizeWizardState(draft: unknown): Partial<TourFormData> {
  const source: Record<string, unknown> = isRecord(draft) ? draft : {};
  const normalized: Record<string, unknown> = { ...source };

  for (const key of STRING_ARRAY_KEYS) {
    normalized[key] = toStringArray(source[key]);
  }

  const rawItinerary = source.itinerary;
  if (rawItinerary === undefined || rawItinerary === null) {
    normalized.itinerary = [];
  } else if (Array.isArray(rawItinerary)) {
    normalized.itinerary = rawItinerary
      .map((entry, index) => normalizeDay(entry, index))
      .filter((day): day is TourItineraryDay => day !== null);
  } else {
    normalized.itinerary = [];
  }

  const rawDifficulty = source.difficulty_level;
  normalized.difficulty_level =
    rawDifficulty === 'easy' || rawDifficulty === 'moderate' || rawDifficulty === 'challenging'
      ? rawDifficulty
      : null;

  for (const key of ['media', 'pricing_tiers', 'availability_rules', 'availability_exceptions'] as const) {
    const value = source[key];
    normalized[key] = Array.isArray(value) ? value : [];
  }

  return normalized as Partial<TourFormData>;
}

/**
 * Merge function for the persisted wizard store: rehydrating an older
 * localStorage snapshot normalizes through the same safe path as
 * loadDraft, so reopened state never crashes array joins/maps and never
 * invents an old difficulty. New-tour defaults (including `easy`) apply
 * only when no persisted form state exists at all.
 */
export function mergePersistedWizardState<S extends WizardState>(
  persisted: unknown,
  current: S
): S {
  if (!isRecord(persisted)) {
    return current;
  }
  const persistedForm = persisted.formData;
  if (!isRecord(persistedForm)) {
    return current;
  }
  const formData = { ...current.formData, ...normalizeWizardState(persistedForm) };
  const step: unknown = persisted.currentStep;
  return {
    ...current,
    formData,
    currentStep:
      step === 'details' || step === 'media' || step === 'pricing' || step === 'availability' || step === 'review'
        ? step
        : current.currentStep,
    isDirty: false,
    isSubmitting: false,
  };
}

export type WizardState = {
  currentStep: WizardStep;
  formData: TourFormData;
  isDirty: boolean;
  isSubmitting: boolean;
};

export type WizardActions = {
  setStep: (step: WizardStep) => void;
  updateField: <K extends keyof TourFormData>(key: K, value: TourFormData[K]) => void;
  updatePricingTier: (id: string, updates: Partial<Omit<PricingTierFormInput, 'id'>>) => void;
  addPricingTier: () => void;
  removePricingTier: (id: string) => void;
  updateAvailabilityRule: (id: string, updates: Partial<Omit<AvailabilityRuleFormInput, 'id'>>) => void;
  addAvailabilityRule: () => void;
  removeAvailabilityRule: (id: string) => void;
  addAvailabilityException: () => void;
  updateAvailabilityException: (id: string, updates: Partial<Omit<AvailabilityExceptionFormInput, 'id'>>) => void;
  removeAvailabilityException: (id: string) => void;
  setMedia: (media: TourMedia[]) => void;
  reset: () => void;
  loadDraft: (draft: Partial<TourFormData>) => void;
  setIsSubmitting: (value: boolean) => void;
};

type WizardStore = WizardState & WizardActions;

function generateId(): string {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

const initialState: WizardState = {
  currentStep: 'details',
  formData: { ...INITIAL_FORM },
  isDirty: false,
  isSubmitting: false,
};

export const useTourWizardStore = create<WizardStore>()(
  persist(
    (set) => ({
      ...initialState,

      setStep: (step) => set({ currentStep: step }),

      updateField: (key, value) =>
        set((state) => ({
          formData: { ...state.formData, [key]: value },
          isDirty: true,
        })),

      updatePricingTier: (id, updates) =>
        set((state) => ({
          formData: {
            ...state.formData,
            pricing_tiers: state.formData.pricing_tiers.map((tier) =>
              tier.id === id ? { ...tier, ...updates } : tier
            ),
          },
          isDirty: true,
        })),

      addPricingTier: () =>
        set((state) => ({
          formData: {
            ...state.formData,
            pricing_tiers: [
              ...state.formData.pricing_tiers,
              {
                id: generateId(),
                name: '',
                price: '',
                currency: 'USD',
                min_participants: 1,
                max_participants: 10,
              },
            ],
          },
          isDirty: true,
        })),

      removePricingTier: (id) =>
        set((state) => ({
          formData: {
            ...state.formData,
            pricing_tiers: state.formData.pricing_tiers.filter((tier) => tier.id !== id),
          },
          isDirty: true,
        })),

      updateAvailabilityRule: (id, updates) =>
        set((state) => ({
          formData: {
            ...state.formData,
            availability_rules: state.formData.availability_rules.map((rule) =>
              rule.id === id ? { ...rule, ...updates } : rule
            ),
          },
          isDirty: true,
        })),

      addAvailabilityRule: () =>
        set((state) => ({
          formData: {
            ...state.formData,
            availability_rules: [
              ...state.formData.availability_rules,
              {
                id: generateId(),
                rule_type: 'weekly',
                days_of_week: [],
                start_time: '09:00',
                start_date: '',
                end_date: '',
                capacity: 10,
              },
            ],
          },
          isDirty: true,
        })),

      removeAvailabilityRule: (id) =>
        set((state) => ({
          formData: {
            ...state.formData,
            availability_rules: state.formData.availability_rules.filter((rule) => rule.id !== id),
          },
          isDirty: true,
        })),

      addAvailabilityException: () =>
        set((state) => ({
          formData: {
            ...state.formData,
            availability_exceptions: [
              ...state.formData.availability_exceptions,
              {
                id: generateId(),
                exception_type: 'specific',
                date: '',
                start_time: '09:00',
                capacity: 10,
                price_multiplier: '1.00',
                note: '',
              },
            ],
          },
          isDirty: true,
        })),

      updateAvailabilityException: (id, updates) =>
        set((state) => ({
          formData: {
            ...state.formData,
            availability_exceptions: state.formData.availability_exceptions.map((exc) =>
              exc.id === id ? { ...exc, ...updates } : exc
            ),
          },
          isDirty: true,
        })),

      removeAvailabilityException: (id) =>
        set((state) => ({
          formData: {
            ...state.formData,
            availability_exceptions: state.formData.availability_exceptions.filter((exc) => exc.id !== id),
          },
          isDirty: true,
        })),

      setMedia: (media) =>
        set((state) => ({
          formData: { ...state.formData, media },
          isDirty: true,
        })),

      reset: () =>
        set({
          ...initialState,
          formData: { ...INITIAL_FORM },
        }),

      loadDraft: (draft) =>
        set((state) => ({
          formData: { ...state.formData, ...normalizeWizardState(draft) },
          isDirty: false,
        })),

      setIsSubmitting: (value) => set({ isSubmitting: value }),
    }),
    {
      name: 'partner-tour-wizard',
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({
        currentStep: state.currentStep,
        formData: state.formData,
      }),
      merge: (persisted, current) => mergePersistedWizardState(persisted, current),
    }
  )
);
