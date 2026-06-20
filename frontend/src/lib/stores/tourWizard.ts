import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
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
          formData: { ...state.formData, ...draft },
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
    }
  )
);