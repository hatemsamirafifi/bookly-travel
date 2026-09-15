'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations, useLocale } from 'next-intl';
import { useQuery } from '@tanstack/react-query';
import { getBooking } from '@/lib/api/partner';
import { NotFoundError } from '@/lib/api/client';
import { BookingDetail } from '@/components/partner/bookings/BookingDetail';
import { PartnerBookingListSkeleton } from '@/components/partner/layout/PartnerSkeleton';

/**
 * Partner booking detail page (blocker 4 frontend surface).
 * Serves the partner-scoped `GET /api/partner/bookings/{reference}` payload
 * through the existing BookingDetail component (status badge, tour/traveler
 * info, mark-completed and cancellation-request actions).
 */
export default function PartnerBookingDetailPage() {
  const t = useTranslations('partner.bookings.detailPage');
  const locale = useLocale();
  const params = useParams<{ reference?: string | string[] }>();
  const raw = params?.reference;
  const reference = Array.isArray(raw) ? raw[0] : (raw ?? '');

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['partner-booking', reference],
    queryFn: () => getBooking(reference),
    enabled: reference.length > 0,
    staleTime: 30_000,
    retry: 1,
  });

  const booking = data?.data ?? null;
  // Partner B cannot open Partner A's booking (and unknown references):
  // the scoped endpoint answers 404, surfaced here as NotFoundError.
  const notFound = error instanceof NotFoundError;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <Link
          href={`/${locale}/partner/bookings`}
          className="text-sm font-medium text-[#0A2540] hover:underline"
        >
          &larr; {t('backToBookings')}
        </Link>
      </div>

      {isLoading ? (
        <PartnerBookingListSkeleton />
      ) : error || !booking ? (
        <div className="rounded-xl border border-gray-200 bg-white p-8 text-center">
          <h1 className="text-lg font-semibold text-[#0A2540]">
            {notFound ? t('notFound') : t('loadError')}
          </h1>
          <button
            type="button"
            onClick={() => refetch()}
            className="mt-4 rounded-lg bg-[#0A2540] px-4 py-2 text-sm font-semibold text-white hover:bg-[#FFB800] hover:text-[#0A2540]"
          >
            {t('retry')}
          </button>
        </div>
      ) : (
        // The BookingDetail component renders its own "Booking Details"
        // heading; no page-level duplicate heading here.
        <BookingDetail booking={booking} />
      )}
    </div>
  );
}
