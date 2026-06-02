'use client';

import { useState } from 'react';
import Link from 'next/link';
import type { PricingInfo, AvailabilityInfo } from '@/lib/api/types';

interface BookingCTAProps {
  pricing: PricingInfo;
  availability: AvailabilityInfo;
  groupSize: { min: number; max: number };
  locale: string;
  slug: string;
}

export default function BookingCTA({ pricing, availability, groupSize, locale, slug }: BookingCTAProps) {
  const [participants, setParticipants] = useState(groupSize.min);

  const isAvailable = availability.next_available_date !== null && availability.available_dates.length > 0;

  return (
    <div className="sticky top-4 rounded-lg border border-gray-200 bg-white p-5 shadow-md">
      <div className="mb-4">
        <span className="text-2xl font-bold text-gray-900">{pricing.base_price.formatted}</span>
        <span className="text-sm text-gray-500"> / person</span>
      </div>

      {isAvailable ? (
        <>
          <div className="mb-3">
            <label htmlFor="participants" className="block text-sm font-medium text-gray-700 mb-1">
              Participants
            </label>
            <div className="flex items-center gap-2">
              <button
                onClick={() => setParticipants(Math.max(groupSize.min, participants - 1))}
                disabled={participants <= groupSize.min}
                className="flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                aria-label="Decrease participants"
              >
                -
              </button>
              <span className="w-10 text-center text-sm font-medium" id="participants-count" aria-live="polite">
                {participants}
              </span>
              <button
                onClick={() => setParticipants(Math.min(groupSize.max, participants + 1))}
                disabled={participants >= groupSize.max}
                className="flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                aria-label="Increase participants"
              >
                +
              </button>
              <span className="ml-2 text-xs text-gray-400">
                {groupSize.min}–{groupSize.max} allowed
              </span>
            </div>
          </div>

          <Link
            href={`/${locale}/booking?tour=${slug}&participants=${participants}&date=${availability.next_available_date}`}
            className="block w-full rounded-xl bg-[#FFB800] py-2.5 text-center text-sm font-semibold text-[#0A2540] hover:bg-[#e6a600] focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:ring-offset-2 transition-colors"
          >
            Book Now
          </Link>

          <p className="mt-2 text-center text-xs text-green-600">
            Next available: {availability.next_available_date}
          </p>
        </>
      ) : (
        <div className="rounded-md bg-gray-100 py-3 text-center" role="status">
          <p className="text-sm font-medium text-gray-500">Currently Unavailable</p>
          <p className="mt-1 text-xs text-gray-400">Check back soon for new dates</p>
          <button
            type="button"
            disabled
            aria-disabled="true"
            className="mt-3 w-full rounded-lg bg-gray-300 py-2.5 text-center text-sm font-semibold text-gray-500 cursor-not-allowed"
          >
            Book Now
          </button>
        </div>
      )}
    </div>
  );
}
