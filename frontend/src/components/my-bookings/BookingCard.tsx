import Link from 'next/link';
import type { BookingResponse } from '@/lib/api/bookings';

interface BookingCardProps {
  booking: BookingResponse;
  locale: string;
}

const statusColors: Record<string, string> = {
  confirmed: 'bg-green-100 text-green-800',
  completed: 'bg-blue-100 text-blue-800',
  cancelled: 'bg-red-100 text-red-800',
  no_show: 'bg-yellow-100 text-yellow-800',
};

export default function BookingCard({ booking, locale }: BookingCardProps) {
  return (
    <Link
      href={`/${locale}/my-bookings/${booking.reference}`}
      className="block rounded-lg border border-gray-200 p-4 hover:border-blue-300 hover:shadow-sm transition-all"
    >
      <div className="flex gap-4">
        {booking.tour.cover_image_url && (
          <img
            src={booking.tour.cover_image_url}
            alt={booking.tour.title}
            className="h-20 w-20 rounded-lg object-cover flex-shrink-0"
            loading="lazy"
          />
        )}
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-2">
            <h3 className="font-semibold text-gray-900 truncate">{booking.tour.title}</h3>
            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${statusColors[booking.status] || 'bg-gray-100 text-gray-800'}`}>
              {booking.status}
            </span>
          </div>
          <p className="mt-1 text-sm text-gray-500">{booking.tour.location}</p>
          <div className="mt-2 flex items-center gap-4 text-sm text-gray-600">
            <span>{new Date(booking.tour_date + 'T00:00:00').toLocaleDateString()}</span>
            <span>{booking.participant_count} {booking.participant_count === 1 ? 'participant' : 'participants'}</span>
          </div>
          <p className="mt-1 text-sm font-semibold text-gray-900">{booking.total_price.formatted}</p>
        </div>
      </div>
    </Link>
  );
}
