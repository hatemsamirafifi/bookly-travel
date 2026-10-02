'use client';

import { useTranslations } from 'next-intl';
import { AnalyticsSummary } from '@/components/partner/analytics/AnalyticsSummary';
import { BookingsChart } from '@/components/partner/analytics/BookingsChart';
import { PartnerAnalyticsSkeleton } from '@/components/partner/layout/PartnerSkeleton';
import { usePartnerAnalytics } from '@/hooks/usePartnerAnalytics';

export default function AnalyticsPage() {
  const t = useTranslations('partner.analytics');
  const dashboard = useTranslations('partner.dashboard');
  const { summary, chartData, loading, error, refetch } = usePartnerAnalytics();

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-bookly-navy">{t('title')}</h1>
      {loading ? (
        <PartnerAnalyticsSkeleton />
      ) : error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-700" role="alert">
          <p>{dashboard('loadError')}</p>
          <button type="button" onClick={refetch} className="mt-3 rounded-lg border border-red-300 bg-white px-4 py-2 font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">
            {dashboard('retry')}
          </button>
        </div>
      ) : summary ? (
        <>
          <AnalyticsSummary summary={summary} />
          <BookingsChart data={chartData} />
        </>
      ) : (
        <div className="rounded-xl border border-border bg-surface p-5 text-sm text-text-muted" role="status">
          {dashboard('noData')}
        </div>
      )}
    </div>
  );
}
