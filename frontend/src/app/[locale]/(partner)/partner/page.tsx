'use client';

import { useEffect, useState } from 'react';
import { useTranslations, useLocale } from 'next-intl';
import Link from 'next/link';
import { CheckCircle2, AlertCircle, Clock } from 'lucide-react';
import { AnalyticsSummary } from '@/components/partner/analytics/AnalyticsSummary';
import { BookingsChart } from '@/components/partner/analytics/BookingsChart';
import { PartnerAnalyticsSkeleton } from '@/components/partner/layout/PartnerSkeleton';
import { usePartnerAnalytics } from '@/hooks/usePartnerAnalytics';
import { getProfile, getOnboardingStatus } from '@/lib/api/partner';
import type { PartnerProfile, PartnerOnboardingStatus } from '@/types/partner';

/**
 * Account-status and onboarding banner. Reflects onboarding_status (pending review / approved / rejected / suspended)
 * and verification state.
 */
function PartnerStatusBanner({
  profile,
  onboardingStatus,
}: {
  profile: PartnerProfile | null;
  onboardingStatus: PartnerOnboardingStatus | null;
}) {
  const t = useTranslations('partner.dashboard.status');
  const locale = useLocale();

  const status = onboardingStatus?.onboarding_status;

  if (status === 'pending' || (profile && !profile.verified && status !== 'approved')) {
    return (
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900 shadow-sm" role="status">
        <div className="flex items-center gap-3">
          <Clock className="h-6 w-6 shrink-0 text-blue-600" />
          <div>
            <p className="font-semibold text-blue-950">
              Account Status: <span className="inline-block rounded-md bg-blue-200 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-blue-800">pending review</span>
            </p>
            <p className="mt-0.5 text-xs text-blue-800">
              Your partner account is under review by our team. You will be notified once verified.
            </p>
          </div>
        </div>
        <Link
          href={`/${locale}/partner/onboarding`}
          className="shrink-0 rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50 shadow-sm"
        >
          View Onboarding Status &rarr;
        </Link>
      </div>
    );
  }

  if (status === 'rejected') {
    return (
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900 shadow-sm" role="alert">
        <div className="flex items-center gap-3">
          <AlertCircle className="h-6 w-6 shrink-0 text-red-600" />
          <div>
            <p className="font-semibold text-red-950">
              Account Status: <span className="inline-block rounded-md bg-red-200 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-red-800">rejected</span>
            </p>
            <p className="mt-0.5 text-xs text-red-800">
              {onboardingStatus?.rejection_reason || 'Your application was not approved. Please review feedback.'}
            </p>
          </div>
        </div>
        <Link
          href={`/${locale}/partner/onboarding`}
          className="shrink-0 rounded-lg bg-red-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-800 shadow-sm"
        >
          Review &amp; Resubmit &rarr;
        </Link>
      </div>
    );
  }

  if (status === 'approved' || profile?.verified) {
    return (
      <div className="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm" role="status">
        <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-600" />
        <div>
          <p className="font-medium">
            Account Status: <span className="font-bold text-emerald-900">active</span> ({t('verified')})
          </p>
          <p className="text-xs text-emerald-700">{t('verifiedDescription')}</p>
        </div>
      </div>
    );
  }

  return null;
}

export default function PartnerDashboardPage() {
  const t = useTranslations('partner.dashboard');
  const { summary, chartData, loading, error, refetch } = usePartnerAnalytics();

  const [profile, setProfile] = useState<PartnerProfile | null>(null);
  const [onboardingStatus, setOnboardingStatus] = useState<PartnerOnboardingStatus | null>(null);

  useEffect(() => {
    let cancelled = false;
    getProfile()
      .then((res) => {
        if (!cancelled) setProfile(res.data);
      })
      .catch(() => {});

    getOnboardingStatus()
      .then((res) => {
        if (!cancelled) setOnboardingStatus(res.data);
      })
      .catch(() => {});

    return () => {
      cancelled = true;
    };
  }, []);

  const isPendingReview = onboardingStatus?.onboarding_status === 'pending' || (profile && !profile.verified && onboardingStatus?.onboarding_status !== 'approved');

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-[#0A2540]">{t('title')}</h1>
      <PartnerStatusBanner profile={profile} onboardingStatus={onboardingStatus} />

      {isPendingReview ? (
        <div className="rounded-xl border border-blue-100 bg-white p-6 shadow-sm">
          <div className="py-6 text-center">
            <h2 className="text-lg font-bold text-[#0A2540]">Welcome to Bookly Partner!</h2>
            <p className="mx-auto mt-2 max-w-md text-sm text-gray-600">
              Your application is currently <strong>pending review</strong>. Full operational features and analytics will become active as soon as your account is approved.
            </p>
          </div>
        </div>
      ) : loading ? (
        <PartnerAnalyticsSkeleton />
      ) : error ? (
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
      ) : !summary ? (
        <div className="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
          {t('noData')}
        </div>
      ) : (
        <>
          <AnalyticsSummary summary={summary} />
          <BookingsChart data={chartData} />
        </>
      )}
    </div>
  );
}