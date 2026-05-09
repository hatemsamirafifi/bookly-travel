'use client';

import Link from 'next/link';

interface DateConfirmationProps {
  date: string;
  tourSlug: string;
  locale: string;
}

export default function DateConfirmation({ date, tourSlug, locale }: DateConfirmationProps) {
  const formattedDate = new Date(date + 'T00:00:00').toLocaleDateString(
    locale === 'en' ? 'en-US' : locale === 'es' ? 'es-ES' : 'it-IT',
    { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }
  );

  return (
    <div className="flex items-center justify-between rounded-lg bg-blue-50 px-4 py-3">
      <div>
        <p className="text-sm font-medium text-blue-900">Selected Date</p>
        <p className="text-lg font-semibold text-blue-700">{formattedDate}</p>
      </div>
      <Link
        href={`/${locale}/tours/${tourSlug}`}
        className="text-sm font-medium text-blue-600 hover:text-blue-800 underline"
      >
        Change date
      </Link>
    </div>
  );
}
