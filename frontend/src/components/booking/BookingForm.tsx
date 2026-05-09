'use client';

import { useState, useEffect } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import ParticipantSelector from './ParticipantSelector';
import DateConfirmation from './DateConfirmation';
import PriceBreakdown from './PriceBreakdown';
import { createBooking } from '@/lib/api/bookings';
import { getTourDetail } from '@/lib/api/tours';
import type { TourDetail } from '@/lib/api/types';

interface BookingFormProps {
  locale: string;
}

export default function BookingForm({ locale }: BookingFormProps) {
  const router = useRouter();
  const searchParams = useSearchParams();

  const tourSlug = searchParams.get('tour') || '';
  const date = searchParams.get('date') || '';

  const [tour, setTour] = useState<TourDetail | null>(null);
  const [participants, setParticipants] = useState(1);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!tourSlug) {
      setLoading(false);
      return;
    }

    getTourDetail(tourSlug, locale)
      .then((res) => {
        setTour(res.data);
        const initialParticipants = parseInt(searchParams.get('participants') || '', 10);
        if (initialParticipants >= res.data.group_size.min && initialParticipants <= res.data.group_size.max) {
          setParticipants(initialParticipants);
        } else {
          setParticipants(res.data.group_size.min);
        }
      })
      .catch(() => setError('Failed to load tour details.'))
      .finally(() => setLoading(false));
  }, [tourSlug, locale, searchParams]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!tourSlug || !date) return;

    setSubmitting(true);
    setError(null);

    try {
      const result = await createBooking({
        tour_slug: tourSlug,
        tour_date: date,
        participant_count: participants,
        locale,
      });

      router.push(`/${locale}/booking/confirmation?ref=${result.data.reference}`);
    } catch (err: any) {
      if (err.status === 409) {
        setError('This tour date is sold out. Please select a different date.');
      } else if (err.status === 422) {
        setError('Invalid booking details. Please check your selection.');
      } else if (err.status === 429) {
        setError('Too many booking attempts. Please wait a moment and try again.');
      } else {
        setError('Something went wrong. Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="animate-pulse space-y-6">
        <div className="h-16 bg-gray-100 rounded-lg" />
        <div className="h-32 bg-gray-100 rounded-lg" />
        <div className="h-24 bg-gray-100 rounded-lg" />
      </div>
    );
  }

  if (!tourSlug || !date) {
    return (
      <div className="text-center py-8">
        <p className="text-gray-500">Please select a tour and date to continue.</p>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <DateConfirmation date={date} tourSlug={tourSlug} locale={locale} />

      {tour && (
        <>
          <ParticipantSelector
            value={participants}
            onChange={setParticipants}
            min={tour.group_size.min}
            max={tour.group_size.max}
            pricePerPerson={tour.pricing.base_price.formatted}
          />

          <PriceBreakdown
            pricePerPerson={tour.pricing.base_price.formatted}
            participantCount={participants}
            total={(() => {
              const totalCents = tour.pricing.base_price.amount * participants;
              const currency = tour.pricing.base_price.currency;
              const symbol = currency === 'EUR' ? '€' : currency === 'USD' ? '$' : '';
              return `${symbol}${(totalCents / 100).toFixed(2)}`;
            })()}
          />
        </>
      )}

      {error && (
        <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700" role="alert">
          {error}
        </div>
      )}

      <button
        type="submit"
        disabled={submitting || !tourSlug || !date}
        className="w-full rounded-lg bg-blue-600 py-3 text-base font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
      >
        {submitting ? 'Confirming...' : 'Confirm Booking'}
      </button>
    </form>
  );
}
