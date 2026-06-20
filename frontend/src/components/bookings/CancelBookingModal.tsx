'use client';

import { useTranslations } from 'next-intl';

interface CancelBookingModalProps {
  open: boolean;
  bookingReference?: string;
  tourName?: string;
  tourDate?: string;
  loading?: boolean;
  error?: string | null;
  onConfirm: () => void;
  onCancel: () => void;
}

export default function CancelBookingModal({
  open,
  bookingReference,
  tourName,
  tourDate,
  loading = false,
  error = null,
  onConfirm,
  onCancel,
}: CancelBookingModalProps) {
  const t = useTranslations('traveler.cancelBooking');

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="cancel-booking-title"
    >
      <div className="w-full max-w-lg rounded-lg bg-white p-5 shadow-xl">
        <h3 id="cancel-booking-title" className="mb-2 text-lg font-semibold text-red-900">{t('title')}</h3>
        <dl className="mb-4 grid gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-800">
          {bookingReference && (
            <div>
              <dt className="inline font-medium">{t('reference')}: </dt>
              <dd className="inline">{bookingReference}</dd>
            </div>
          )}
          {tourName && (
            <div>
              <dt className="inline font-medium">{t('tour')}: </dt>
              <dd className="inline">{tourName}</dd>
            </div>
          )}
          {tourDate && (
            <div>
              <dt className="inline font-medium">{t('date')}: </dt>
              <dd className="inline">{tourDate}</dd>
            </div>
          )}
        </dl>
        <p className="mb-2 text-sm text-gray-700">{t('body')}</p>
        <p className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm font-medium text-amber-900">
          {t('windowWarning')}
        </p>
        {error && (
          <p className="mb-3 text-sm text-red-600" role="alert">{error}</p>
        )}
        <div className="flex flex-wrap gap-2">
          <button
            onClick={onConfirm}
            disabled={loading}
            className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
          >
            {loading ? t('cancelling') : t('confirm')}
          </button>
          <button
            onClick={onCancel}
            disabled={loading}
            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            {t('keep')}
          </button>
        </div>
      </div>
    </div>
  );
}
