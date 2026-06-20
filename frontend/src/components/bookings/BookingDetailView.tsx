import Link from 'next/link';
import { useTranslations } from 'next-intl';
import BookingStatusBadge from '@/components/my-bookings/BookingStatusBadge';
import type { TravelerBooking } from '@/types/traveler';

interface BookingDetailViewProps {
  booking: TravelerBooking;
  locale: string;
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <p className="text-sm text-gray-500">{label}</p>
      <div className="text-sm text-gray-900">{children}</div>
    </div>
  );
}

function formatMoneyValue(value?: number | { amount?: number; formatted?: string }) {
  if (typeof value === 'object' && value?.formatted) return value.formatted;
  const amount = typeof value === 'object' ? value.amount : value;
  if (typeof amount !== 'number') return '';
  return new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' }).format(amount / 100);
}

export default function BookingDetailView({ booking, locale }: BookingDetailViewProps) {
  const detailT = useTranslations('traveler.bookingDetail');
  const tourName = booking.tour.name || booking.tour.title || detailT('fallbackTour');
  const participants = booking.participants ?? booking.participant_count ?? 1;
  const total = formatMoneyValue(booking.total_amount ?? booking.total_price);
  const pricePerPerson = formatMoneyValue(booking.price_per_person);

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-sm text-gray-500">{detailT('reference')}</p>
          <p className="font-mono text-lg font-semibold text-gray-900">{booking.reference}</p>
        </div>
        <BookingStatusBadge status={booking.status} />
      </div>

      <div className="mt-5 grid gap-4 sm:grid-cols-2">
        <Field label={detailT('tour')}>
          <Link href={`/${locale}/tours/${booking.tour.slug}`} className="font-semibold text-[#0A2540] underline">
            {tourName}
          </Link>
          <p className="text-sm text-gray-600">{booking.tour.location}</p>
        </Field>
        <Field label={detailT('dateTime')}>
          <p>{booking.tour_date}{booking.tour_time ? ` at ${booking.tour_time}` : ''}</p>
        </Field>
        <Field label={detailT('participants')}>
          <p>{participants}</p>
        </Field>
        <Field label={detailT('price')}>
          <p>{detailT('perPerson', { price: pricePerPerson })}</p>
          <p className="font-semibold">{detailT('total', { total })}</p>
        </Field>
      </div>

      {booking.special_requests && (
        <Field label={detailT('specialRequests')}>
          <p>{booking.special_requests}</p>
        </Field>
      )}
    </div>
  );
}
