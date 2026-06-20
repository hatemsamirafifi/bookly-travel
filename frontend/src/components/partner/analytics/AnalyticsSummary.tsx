'use client';

import { useTranslations } from 'next-intl';
import { TrendingUp, Users, Star, DollarSign, CalendarClock, MessageSquare } from 'lucide-react';
import type { AnalyticsSummary as AnalyticsSummaryData } from '@/types/partner';

interface SummaryProps {
  summary: AnalyticsSummaryData;
}

interface StatCard {
  label: string;
  value: string;
  icon: typeof Users;
  color: string;
  bg: string;
}

/** Formats a raw revenue integer as currency, defaulting to EUR (the backend returns no currency). */
function formatRevenue(revenue: number): string {
  const amount = typeof revenue === 'number' ? revenue : 0;
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' }).format(amount);
  } catch {
    return `€${amount.toFixed(2)}`;
  }
}

export function AnalyticsSummary({ summary }: SummaryProps) {
  const t = useTranslations('partner.dashboard');
  // The API returns conversion_rate as a percentage (e.g. 3.4 for 3.4%).
  const conversionPct = typeof summary.conversion_rate === 'number' ? summary.conversion_rate : 0;

  const cards: StatCard[] = [
    { label: t('totalBookings'), value: String(summary.total_bookings ?? 0), icon: Users, color: 'text-blue-600', bg: 'bg-blue-50' },
    { label: t('totalRevenue'), value: formatRevenue(summary.total_revenue), icon: DollarSign, color: 'text-emerald-600', bg: 'bg-emerald-50' },
    { label: t('averageRating'), value: (summary.average_rating ?? 0).toFixed(1), icon: Star, color: 'text-amber-600', bg: 'bg-amber-50' },
    { label: t('conversionRate'), value: `${conversionPct.toFixed(1)}%`, icon: TrendingUp, color: 'text-violet-600', bg: 'bg-violet-50' },
  ];

  // Optional metrics the backend does not currently return — render only when present.
  if (typeof summary.upcoming_bookings === 'number') {
    cards.push({ label: t('upcomingBookings'), value: String(summary.upcoming_bookings), icon: CalendarClock, color: 'text-indigo-600', bg: 'bg-indigo-50' });
  }
  if (typeof summary.review_count === 'number') {
    cards.push({ label: t('reviewCount'), value: String(summary.review_count), icon: MessageSquare, color: 'text-rose-600', bg: 'bg-rose-50' });
  }

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      {cards.map((card) => (
        <div key={card.label} className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm font-medium text-gray-500">{card.label}</span>
            <div className={`p-2 rounded-lg ${card.bg}`}>
              <card.icon className={`w-4 h-4 ${card.color}`} />
            </div>
          </div>
          <span className="text-2xl font-bold text-[#0A2540]">{card.value}</span>
        </div>
      ))}
    </div>
  );
}