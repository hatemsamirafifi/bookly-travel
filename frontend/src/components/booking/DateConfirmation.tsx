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
    <div className="flex items-center justify-between rounded-lg bg-[#F7F9FB] border border-gray-200 px-4 py-3">
      <div>
        <p className="text-sm font-medium text-[#0A2540]">Selected Date</p>
        <p className="text-lg font-semibold text-[#0A2540]">{formattedDate}</p>
      </div>
      <Link
        href={`/${locale}/tours/${tourSlug}`}
        className="text-sm font-medium text-[#0A2540] hover:text-[#071b2e] underline"
      >
        Change date
      </Link>
    </div>
  );
}
