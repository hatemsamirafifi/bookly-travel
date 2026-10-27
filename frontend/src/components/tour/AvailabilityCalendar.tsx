'use client';

import { useTranslations } from 'next-intl';
import { formatOfferedDateUtc } from '@/lib/tours/booking-selection';

export interface AvailabilityCalendarProps {
  offeredDates: string[];
  selectedDate: string | null;
  onSelect: (date: string | null) => void;
  nextAvailableDate: string | null;
  locale?: string;
  id: string;
  disabled?: boolean;
}

/** Controlled offered-date selector (Spec 020 US2). All selection state lives
 *  in TourBookingController; this component renders every offered future date
 *  through one labelled native select with UTC day labels. A selected value
 *  that left the offered set stays visible as a disabled option with its
 *  explanation rendered by the caller — never silently substituted. */
export default function AvailabilityCalendar({
  offeredDates,
  selectedDate,
  onSelect,
  nextAvailableDate,
  locale = 'en',
  id,
  disabled = false,
}: AvailabilityCalendarProps) {
  const t = useTranslations('tour');

  if (offeredDates.length === 0) {
    return (
      <div className="rounded-lg border border-warning/30 bg-warning/10 p-4">
        <p className="text-sm font-medium text-warning">{t('unavailable')}</p>
        <p className="mt-1 text-sm text-warning">{t('unavailableHint')}</p>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-border bg-surface p-4">
      <label htmlFor={id} className="mb-3 block text-sm font-semibold text-primary">
        {t('selectDate')}
      </label>

      <div className="mb-3">
        <p className="text-xs text-text-muted">{t('nextAvailable')}</p>
        {nextAvailableDate && (
          <p className="text-sm font-medium text-trust">{formatOfferedDateUtc(nextAvailableDate, locale)}</p>
        )}
      </div>

      <select
        id={id}
        value={selectedDate ?? ''}
        disabled={disabled}
        onChange={(event) => onSelect(event.target.value === '' ? null : event.target.value)}
        className="w-full rounded-md border border-border bg-surface px-2 py-2 text-sm text-primary"
      >
        <option value="">{t('selectDate')}</option>
        {selectedDate !== null && !offeredDates.includes(selectedDate) && (
          <option value={selectedDate} disabled>
            {formatOfferedDateUtc(selectedDate, locale)}
          </option>
        )}
        {offeredDates.map((date) => (
          <option key={date} value={date}>
            {formatOfferedDateUtc(date, locale)}
          </option>
        ))}
      </select>
    </div>
  );
}
