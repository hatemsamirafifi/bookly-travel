import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';

interface CheckoutState {
  tourSlug: string;
  selectedDate: string;
  participants: number;
  pricePerPerson: number;
  currency: string;
  currentStep: number;
  idempotencyKey: string;
  guestName: string;
  guestEmail: string;
  guestPhone: string;
  specialRequests: string;
  setTourSlug: (slug: string) => void;
  setSelectedDate: (date: string) => void;
  setParticipants: (count: number) => void;
  setPricePerPerson: (price: number, currency: string) => void;
  setCurrentStep: (step: number) => void;
  setGuestDetails: (details: Partial<Pick<CheckoutState, 'guestName' | 'guestEmail' | 'guestPhone' | 'specialRequests'>>) => void;
  reset: () => void;
}

const initialState: Omit<
  CheckoutState,
  | 'setTourSlug'
  | 'setSelectedDate'
  | 'setParticipants'
  | 'setPricePerPerson'
  | 'setCurrentStep'
  | 'setGuestDetails'
  | 'reset'
> = {
  tourSlug: '',
  selectedDate: '',
  participants: 1,
  pricePerPerson: 0,
  currency: 'USD',
  currentStep: 1,
  idempotencyKey: '',
  guestName: '',
  guestEmail: '',
  guestPhone: '',
  specialRequests: '',
};

export const useCheckoutStore = create<CheckoutState>()(
  persist(
    (set) => ({
      ...initialState,
      idempotencyKey: typeof crypto !== 'undefined' ? crypto.randomUUID() : '',
      setTourSlug: (slug) => set({ tourSlug: slug }),
      setSelectedDate: (date) => set({ selectedDate: date }),
      setParticipants: (count) => set({ participants: count }),
      setPricePerPerson: (price, currency) => set({ pricePerPerson: price, currency }),
      setCurrentStep: (step) => set({ currentStep: step }),
      setGuestDetails: (details) => set((state) => ({ ...state, ...details })),
      reset: () => set({ ...initialState, idempotencyKey: typeof crypto !== 'undefined' ? crypto.randomUUID() : '' }),
    }),
    {
      name: 'bookly-checkout',
      storage: createJSONStorage(() => sessionStorage),
      partialize: (state) => ({
        tourSlug: state.tourSlug,
        selectedDate: state.selectedDate,
        participants: state.participants,
        pricePerPerson: state.pricePerPerson,
        currency: state.currency,
        currentStep: state.currentStep,
        idempotencyKey: state.idempotencyKey,
        guestName: state.guestName,
        guestEmail: state.guestEmail,
        guestPhone: state.guestPhone,
        specialRequests: state.specialRequests,
      }),
    }
  )
);
