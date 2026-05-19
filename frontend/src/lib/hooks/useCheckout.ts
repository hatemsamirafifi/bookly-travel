'use client';

import { useCallback } from 'react';
import { useCheckoutStore } from '@/lib/stores/checkout-store';
import { createBooking } from '@/lib/api/bookings';
import type { CreateBookingRequest } from '@/lib/api/bookings';

export function useCheckout() {
  const store = useCheckoutStore();

  const initCheckout = useCallback(
    (tourSlug: string, date: string, participants: number, pricePerPerson: number, currency: string) => {
      store.setTourSlug(tourSlug);
      store.setSelectedDate(date);
      store.setParticipants(participants);
      store.setPricePerPerson(pricePerPerson, currency);
      store.setCurrentStep(1);
    },
    [store]
  );

  const submitBooking = useCallback(
    async (params: Omit<CreateBookingRequest, 'locale'> & { locale: string }) => {
      return createBooking(params);
    },
    []
  );

  const clearCheckout = useCallback(() => {
    store.reset();
  }, [store]);

  return {
    ...store,
    initCheckout,
    submitBooking,
    clearCheckout,
  };
}
