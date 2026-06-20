import Image from 'next/image';
import Link from 'next/link';
import BookingStatusBadge from './BookingStatusBadge';
import type { TravelerBooking } from '@/types/traveler';

interface BookingCardProps {
  booking: TravelerBooking;
  locale: string;
}

export default function BookingCard({ booking, locale }: BookingCardProps) {
  const tourName = booking.tour.name || booking.tour.title || 'Tour';
  const coverImage = booking.tour.cover_image || booking.tour.cover_image_url;
  const participants = booking.participants ?? booking.participant_count ?? 1;
  const total = typeof booking.total_amount === 'object'
    ? booking.total_amount.formatted
    : booking.total_price?.formatted || formatMoney(booking.total_amount);

  return (
    <Link
      href={`/${locale}/my-bookings/${booking.reference}`}
      data-testid="booking-card"
      className="block rounded-lg border border-gray-200 bg-white p-4 transition-all hover:border-[#FFB800] hover:shadow-sm"
    >
      <div className="flex gap-4">
        {coverImage && (
          <Image
            src={coverImage}
            alt={tourName}
            width={80}
            height={80}
            className="h-20 w-20 flex-shrink-0 rounded-lg object-cover"
          />
        )}
        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-2">
            <h3 className="truncate font-semibold text-gray-900">{tourName}</h3>
            <BookingStatusBadge status={booking.status} />
          </div>
          <p className="mt-1 text-sm text-gray-500">{booking.tour.location}</p>
          <div className="mt-2 flex flex-wrap items-center gap-4 text-sm text-gray-600">
            <span>{new Date(`${booking.tour_date}T00:00:00`).toLocaleDateString()}</span>
            <span>{participants} {participants === 1 ? 'participant' : 'participants'}</span>
          </div>
          <p className="mt-1 text-sm font-semibold text-gray-900">{total}</p>
        </div>
      </div>
    </Link>
  );
}

function formatMoney(amount?: number) {
  if (typeof amount !== 'number') return '';
  return new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' }).format(amount / 100);
}
