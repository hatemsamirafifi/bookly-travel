'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CheckCircle2, AlertCircle } from 'lucide-react';
import { AnalyticsSummary } from '@/components/partner/analytics/AnalyticsSummary';
import { BookingsChart } from '@/components/partner/analytics/BookingsChart';
import { PartnerAnalyticsSkeleton } from '@/components/partner/layout/PartnerSkeleton';
import { usePartnerAnalytics } from '@/hooks/usePartnerAnalytics';
import { getProfile } from '@/lib/api/partner';
import type { PartnerProfile } from '@/types/partner';

/**
 * Account-status banner. The partner data model exposes only `verified: boolean`
 * (no pending/approved/rejected tri-state for the account), so the banner
 * reflects verified vs. not-yet-verified.
 */
function PartnerStatusBanner({ profile }: { profile: PartnerProfile | null }) {
  const t = useTranslations('partner.dashboard.status');
  if (!profile) return null;
  if (profile.verified) {
    return (
      <div className="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-600" />
        <div>
          <p className="font-medium">{t('verified')}</p>
          <p className="text-emerald-700">{t('verifiedDescription')}</p>
        </div>
      </div>
    );
  }
  return (
    <div className="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      <AlertCircle className="h-5 w-5 shrink-0 text-amber-600" />
      <div>
        <p className="font-medium">{t('notVerified')}</p>
        <p className="text-amber-700">{t('notVerifiedDescription')}</p>
      </div>
    </div>
  );
}

export default function PartnerDashboardPage() {
  const t = useTranslations('partner.dashboard');
  const { summary, chartData, loading, error, refetch } = usePartnerAnalytics();

  const [profile, setProfile] = useState<PartnerProfile | null>(null);

  useEffect(() => {
    let cancelled = false;
    getProfile()
      .then((res) => {
        if (!cancelled) setProfile(res.data);
      })
      .catch(() => {
        /* banner is non-critical; absence just hides it */
      });
    return () => {
      cancelled = true;
    };
  }, []);

  if (loading) {
    return (
      <div className="space-y-6">
        <PartnerStatusBanner profile={profile} />
        <PartnerAnalyticsSkeleton />
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <PartnerStatusBanner profile={profile} />
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
          <p>{t('loadError')}</p>
          <button
            type="button"
            onClick={refetch}
            className="mt-2 inline-flex items-center rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
          >
            {t('retry')}
          </button>
        </div>
      </div>
    );
  }

  if (!summary) {
    return (
      <div className="space-y-6">
        <PartnerStatusBanner profile={profile} />
        <div className="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
          {t('noData')}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PartnerStatusBanner profile={profile} />
      <AnalyticsSummary summary={summary} />
      <BookingsChart data={chartData} />
    </div>
  );
}