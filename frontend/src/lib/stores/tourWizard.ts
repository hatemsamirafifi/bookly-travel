import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import { isPlaceholderOptionRow } from '@/lib/validators/partner';
import type { TourItineraryDay } from '@/lib/api/types';
import type {
  WizardStep,
  TourFormData,
  PricingTierFormInput,
  AvailabilityRuleFormInput,
  AvailabilityExceptionFormInput,
  BookingOptionFormInput,
  TourMedia,
} from '@/types/tour';

// Inline the initial data to avoid circular issues at module init
const INITIAL_FORM: TourFormData = {
  source_locale: null,
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
  options: [],
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

/** Guard a restored option row without fabricating facts: non-object or
 *  untyped rows keep blank text/numbers, numeric values restore as their
 *  decimal string, arrays are filtered to their real members. */
function normalizeOption(entry: unknown, index: number): BookingOptionFormInput | null {
  if (!isRecord(entry) || Array.isArray(entry)) {
    return null;
  }
  const toNumberString = (value: unknown): string =>
    typeof value === 'string' ? value : typeof value === 'number' && Number.isFinite(value) ? String(value) : '';
  const centsToMajorString = (cents: unknown): string => {
    if (typeof cents !== 'number' || !Number.isFinite(cents) || cents <= 0) {
      return '';
    }
    return (cents / 100).toFixed(2).replace(/\.?0+$/, '');
  };
  const localId = typeof entry.id === 'string' && entry.id.trim() !== '' ? entry.id : null;
  const serverId =
    typeof entry.server_id === 'number' && Number.isSafeInteger(entry.server_id) && entry.server_id > 0
      ? entry.server_id
      : typeof entry.id === 'number' && Number.isSafeInteger(entry.id) && entry.id > 0
        ? entry.id
        : undefined;
  const price = entry.price !== undefined ? toNumberString(entry.price) : centsToMajorString(entry.price_amount);
  const capacity = entry.capacity !== undefined ? toNumberString(entry.capacity) : toNumberString(entry.group_size_max);
  const rawStartTime = typeof entry.start_time === 'string' ? entry.start_time : '';
  const startTime = /^\d{2}:\d{2}:\d{2}$/.test(rawStartTime) ? rawStartTime.slice(0, 5) : rawStartTime;
  const row: BookingOptionFormInput = {
    id: localId ?? (serverId !== undefined ? `srv-option-${serverId}` : `restored-${index}`),
    server_id: serverId,
    title: typeof entry.title === 'string' ? entry.title : '',
    pickup_instructions: typeof entry.pickup_instructions === 'string' ? entry.pickup_instructions : '',
    guide_languages: Array.isArray(entry.guide_languages)
      ? entry.guide_languages.filter((code): code is string => typeof code === 'string')
      : [],
    price,
    capacity,
    start_time: startTime,
    weekdays: Array.isArray(entry.weekdays)
      ? [...new Set(entry.weekdays.filter((day): day is number => typeof day === 'number' && Number.isInteger(day) && day >= 0 && day <= 6))]
      : [],
  };
  return localId === null && row.server_id === undefined && isPlaceholderOptionRow(row) ? null : row;
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

  // Original writing language: only a concrete supported code is an actual
  // choice. Anything missing/null/invalid stays UNSELECTED so a restored
  // unbound snapshot re-enters through the Language gate instead of being
  // silently preselected (EGM-004). The English default for identifiable
  // materialized legacy tours is applied by the edit-flow call sites that
  // know a real tour is being read — never here.
  const rawSourceLocale = source.source_locale;
  normalized.source_locale =
    rawSourceLocale === 'en' || rawSourceLocale === 'es' || rawSourceLocale === 'it'
      ? rawSourceLocale
      : null;

  for (const key of STRING_ARRAY_KEYS) {
    if (key === 'languages' && source.languages === undefined && source.guide_languages !== undefined) {
      normalized.languages = toStringArray(source.guide_languages);
    } else {
      normalized[key] = toStringArray(source[key]);
    }
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

  const rawMedia = source.media;
  normalized.media = Array.isArray(rawMedia)
    ? rawMedia
        .filter((item): item is Record<string, unknown> => isRecord(item) && typeof item.url === 'string' && item.url.trim() !== '')
        .map((item, index) => ({
          id: typeof item.id === 'string' && item.id.trim() !== '' ? item.id : `restored-media-${index}`,
          url: String(item.url),
          is_cover: Boolean(item.is_cover),
          sort_order: typeof item.sort_order === 'number' && Number.isFinite(item.sort_order) ? item.sort_order : index,
          ...(item.alt_text !== undefined
            ? { alt_text: typeof item.alt_text === 'string' ? item.alt_text : null }
            : {}),
        }))
    : [];

  const rawTiers = source.pricing_tiers;
  normalized.pricing_tiers = Array.isArray(rawTiers)
    ? rawTiers
        .filter((item): item is Record<string, unknown> => isRecord(item))
        .map((item, index) => ({
          id: typeof item.id === 'string' && item.id.trim() !== '' ? item.id : `restored-tier-${index}`,
          name: typeof item.name === 'string' ? item.name : '',
          price:
            typeof item.price === 'string'
              ? item.price
              : typeof item.price === 'number' && Number.isFinite(item.price)
                ? String(item.price)
                : '0',
          currency: typeof item.currency === 'string' && item.currency.trim() !== '' ? item.currency : 'EUR',
          min_participants:
            typeof item.min_participants === 'number' && Number.isFinite(item.min_participants) ? item.min_participants : 1,
          max_participants:
            typeof item.max_participants === 'number' && Number.isFinite(item.max_participants)
              ? item.max_participants
              : null,
        }))
    : [];

  for (const key of ['availability_rules', 'availability_exceptions'] as const) {
    const value = source[key];
    normalized[key] = Array.isArray(value) ? value : [];
  }

  const rawOptions = source.options;
  normalized.options = Array.isArray(rawOptions)
    ? rawOptions
        .map((entry, index) => normalizeOption(entry, index))
        .filter((option): option is BookingOptionFormInput => option !== null)
    : [];

  return normalized as Partial<TourFormData>;
}

export function toServerWizardStep(step: WizardStep): string {
  return step === 'source' ? 'language' : step;
}

export function fromServerWizardStep(step: unknown): WizardStep {
  if (step === 'language' || step === 'source') return 'source';
  if (step === 'details' || step === 'media' || step === 'pricing' || step === 'availability' || step === 'review') {
    return step;
  }
  return 'source';
}

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export function isUuid(value: unknown): value is string {
  return typeof value === 'string' && UUID_RE.test(value);
}

export function generateUuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

/**
 * Merge function for the persisted wizard store: rehydrating an older
 * localStorage snapshot normalizes through the same safe path as
 * loadDraft, so reopened state never crashes array joins/maps and never
 * invents an old difficulty. New-tour defaults (including `easy`) apply
 * only when no persisted form state exists at all. A restored draft whose
 * writing language is still unanswered re-enters at the Language gate even
 * when an older step was persisted.
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
  const persistedStep =
    step === 'source' || step === 'details' || step === 'media' || step === 'pricing' || step === 'availability' || step === 'review'
      ? step
      : current.currentStep;
  const draftId =
    typeof persisted.draftId === 'number' && Number.isSafeInteger(persisted.draftId) && persisted.draftId > 0
      ? persisted.draftId
      : null;
  const tourId =
    typeof persisted.tourId === 'number' && Number.isSafeInteger(persisted.tourId) && persisted.tourId > 0
      ? persisted.tourId
      : null;
  const creationId = isUuid(persisted.creationId)
    ? persisted.creationId
    : isUuid(current.creationId)
      ? current.creationId
      : generateUuid();
  const concurrencyToken =
    typeof persisted.concurrencyToken === 'string' && persisted.concurrencyToken.trim() !== ''
      ? persisted.concurrencyToken
      : null;
  const lastSyncedAt =
    typeof persisted.lastSyncedAt === 'string' && persisted.lastSyncedAt.trim() !== ''
      ? persisted.lastSyncedAt
      : null;
  const hasUnsyncedDraftChanges =
    typeof persisted.hasUnsyncedDraftChanges === 'boolean' ? persisted.hasUnsyncedDraftChanges : false;

  return {
    ...current,
    formData,
    // An unselected restored draft always re-enters through the Language
    // gate, even when an older step was persisted alongside it (EGM-004).
    // A chosen valid source keeps the persisted step.
    currentStep: formData.source_locale === null ? 'source' : persistedStep,
    draftId,
    creationId,
    tourId,
    concurrencyToken,
    lastSyncedAt,
    hasUnsyncedDraftChanges,
    isDirty: false,
    isSubmitting: false,
  };
}

export type WizardState = {
  currentStep: WizardStep;
  formData: TourFormData;
  draftId: number | null;
  creationId: string;
  tourId: number | null;
  concurrencyToken: string | null;
  lastSyncedAt: string | null;
  hasUnsyncedDraftChanges: boolean;
  isDirty: boolean;
  isSubmitting: boolean;
};

export interface ServerDraftHydrationInput {
  id?: number | null;
  draft_id?: number | null;
  creation_id?: string | null;
  tour_id?: number | null;
  concurrency_token?: string | null;
  current_step?: string | null;
  source_locale?: string | null;
  auto_saved_at?: string | null;
  updated_at?: string | null;
  payload?: unknown;
}

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
  updateBookingOption: (id: string, updates: Partial<Omit<BookingOptionFormInput, 'id'>>) => void;
  addBookingOption: () => void;
  removeBookingOption: (id: string) => void;
  /** Adopt the persistent option ids echoed by an owned tour write, so the
   *  next update in the same wizard session edits the stored rows instead of
   *  duplicating them. Matching is positional over the placeholder-filtered
   *  rows — the submitted rows are stored in submission order. */
  adoptSavedOptionIds: (saved: Array<{ id: unknown; title: unknown }> | null | undefined) => void;
  setMedia: (media: TourMedia[]) => void;
  setDraftSyncMeta: (meta: {
    draftId?: number | null;
    creationId?: string;
    tourId?: number | null;
    concurrencyToken?: string | null;
    lastSyncedAt?: string | null;
    hasUnsyncedDraftChanges?: boolean;
  }) => void;
  hydrateFromServerDraft: (draft: ServerDraftHydrationInput) => void;
  regenerateCreationId: () => void;
  reset: () => void;
  loadDraft: (draft: Partial<TourFormData>) => void;
  setIsSubmitting: (value: boolean) => void;
};

type WizardStore = WizardState & WizardActions;

function generateId(): string {
  return generateUuid();
}

const initialState: WizardState = {
  // The Language (source) screen is the wizard's first step: a fresh or
  // reset form always starts there, never past the writing-language gate.
  currentStep: 'source',
  formData: { ...INITIAL_FORM },
  draftId: null,
  creationId: generateUuid(),
  tourId: null,
  concurrencyToken: null,
  lastSyncedAt: null,
  hasUnsyncedDraftChanges: false,
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
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
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
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
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
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
        })),

      removePricingTier: (id) =>
        set((state) => ({
          formData: {
            ...state.formData,
            pricing_tiers: state.formData.pricing_tiers.filter((tier) => tier.id !== id),
          },
          isDirty: true,
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
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
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
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
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
        })),

      removeAvailabilityRule: (id) =>
        set((state) => ({
          formData: {
            ...state.formData,
            availability_rules: state.formData.availability_rules.filter((rule) => rule.id !== id),
          },
          isDirty: true,
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
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
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
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
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
        })),

      removeAvailabilityException: (id) =>
        set((state) => ({
          formData: {
            ...state.formData,
            availability_exceptions: state.formData.availability_exceptions.filter((exc) => exc.id !== id),
          },
          isDirty: true,
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
        })),

      updateBookingOption: (id, updates) =>
        set((state) => ({
          formData: {
            ...state.formData,
            options: state.formData.options.map((option) =>
              option.id === id ? { ...option, ...updates } : option
            ),
          },
          isDirty: true,
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
        })),

      addBookingOption: () =>
        set((state) => ({
          formData: {
            ...state.formData,
            options: [
              ...state.formData.options,
              {
                id: generateId(),
                title: '',
                pickup_instructions: '',
                guide_languages: [],
                price: '',
                capacity: '',
                start_time: '',
                weekdays: [],
              },
            ],
          },
          isDirty: true,
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
        })),

      removeBookingOption: (id) =>
        set((state) => ({
          formData: {
            ...state.formData,
            options: state.formData.options.filter((option) => option.id !== id),
          },
          isDirty: true,
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
        })),

      adoptSavedOptionIds: (saved) =>
        set((state) => {
          if (!Array.isArray(saved) || saved.length === 0) {
            return state;
          }
          const submittedIndexes: number[] = [];
          state.formData.options.forEach((row, index) => {
            if (!isPlaceholderOptionRow(row)) {
              submittedIndexes.push(index);
            }
          });
          let changed = false;
          const nextOptions = [...state.formData.options];
          submittedIndexes.forEach((rowIndex, savedIndex) => {
            const savedOption = saved[savedIndex];
            if (!isRecord(savedOption)) return;
            const savedId = savedOption.id;
            const row = nextOptions[rowIndex];
            if (
              typeof savedId === 'number' &&
              Number.isSafeInteger(savedId) &&
              row &&
              row.server_id !== savedId
            ) {
              nextOptions[rowIndex] = { ...row, server_id: savedId };
              changed = true;
            }
          });
          if (!changed) {
            return state;
          }
          return {
            formData: { ...state.formData, options: nextOptions },
            isDirty: state.isDirty,
          };
        }),

      setMedia: (media) =>
        set((state) => ({
          formData: { ...state.formData, media },
          isDirty: true,
          hasUnsyncedDraftChanges: state.draftId !== null ? true : state.hasUnsyncedDraftChanges,
        })),

      setDraftSyncMeta: (meta) =>
        set((state) => ({
          draftId: meta.draftId !== undefined ? meta.draftId : state.draftId,
          creationId: meta.creationId !== undefined ? meta.creationId : state.creationId,
          tourId: meta.tourId !== undefined ? meta.tourId : state.tourId,
          concurrencyToken: meta.concurrencyToken !== undefined ? meta.concurrencyToken : state.concurrencyToken,
          lastSyncedAt: meta.lastSyncedAt !== undefined ? meta.lastSyncedAt : state.lastSyncedAt,
          hasUnsyncedDraftChanges: meta.hasUnsyncedDraftChanges ?? false,
          isDirty: meta.hasUnsyncedDraftChanges ?? false,
        })),

      hydrateFromServerDraft: (draft) =>
        set((state) => {
          const normalized = normalizeWizardState(draft.payload);
          const explicitLocale =
            draft.source_locale === 'en' || draft.source_locale === 'es' || draft.source_locale === 'it'
              ? draft.source_locale
              : draft.source_locale === null
                ? null
                : normalized.source_locale ?? null;
          const nextFormData: TourFormData = {
            ...INITIAL_FORM,
            ...normalized,
            source_locale: explicitLocale,
          };
          const resolvedDraftId =
            typeof draft.id === 'number' && Number.isSafeInteger(draft.id) && draft.id > 0
              ? draft.id
              : typeof draft.draft_id === 'number' && Number.isSafeInteger(draft.draft_id) && draft.draft_id > 0
                ? draft.draft_id
                : state.draftId;
          const resolvedTourId =
            typeof draft.tour_id === 'number' && Number.isSafeInteger(draft.tour_id) && draft.tour_id > 0
              ? draft.tour_id
              : null;
          return {
            formData: nextFormData,
            currentStep: explicitLocale === null ? 'source' : fromServerWizardStep(draft.current_step),
            draftId: resolvedDraftId,
            creationId: isUuid(draft.creation_id) ? draft.creation_id : state.creationId,
            tourId: resolvedTourId,
            concurrencyToken: draft.concurrency_token ?? null,
            lastSyncedAt: draft.auto_saved_at ?? draft.updated_at ?? state.lastSyncedAt,
            hasUnsyncedDraftChanges: false,
            isDirty: false,
            isSubmitting: false,
          };
        }),

      regenerateCreationId: () =>
        set({
          draftId: null,
          creationId: generateUuid(),
          tourId: null,
          concurrencyToken: null,
          lastSyncedAt: null,
          hasUnsyncedDraftChanges: false,
        }),

      reset: () =>
        set({
          ...initialState,
          creationId: generateUuid(),
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
        draftId: state.draftId,
        creationId: state.creationId,
        tourId: state.tourId,
        concurrencyToken: state.concurrencyToken,
        lastSyncedAt: state.lastSyncedAt,
        hasUnsyncedDraftChanges: state.hasUnsyncedDraftChanges,
      }),
      merge: (persisted, current) => mergePersistedWizardState(persisted, current),
    }
  )
);
