'use client';

import React, { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useTranslations, useLocale } from 'next-intl';
import { useAuth } from '@/lib/hooks/useAuth';
import { getOnboardingStatus } from '@/lib/api/partner';
import type { PartnerOnboardingStatus } from '@/types/partner';
import { OnboardingStatusBanner } from '@/components/partner/OnboardingStatusBanner';

export default function PartnerOnboardingPage() {
  const locale = useLocale();
  const router = useRouter();
  const { user, isLoading: isAuthLoading } = useAuth();
  const isAuthenticated = Boolean(user);

  const [statusData, setStatusData] = useState<PartnerOnboardingStatus | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState<string | null>(null);

  useEffect(() => {
    if (!isAuthLoading && !isAuthenticated) {
      router.push(`/${locale}/auth/login?returnUrl=/${locale}/partner/onboarding`);
      return;
    }

    if (isAuthenticated) {
      fetchStatus();
    }
  }, [isAuthenticated, isAuthLoading, locale, router]);

  const fetchStatus = async () => {
    setIsLoading(true);
    setFetchError(null);
    try {
      const res = await getOnboardingStatus();
      if (res?.data) {
        setStatusData(res.data);
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : (err as { message?: string })?.message;
      setFetchError(message || 'Failed to load onboarding status.');
    } finally {
      setIsLoading(false);
    }
  };

  if (isAuthLoading || isLoading) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center p-8">
        <div className="flex flex-col items-center gap-3">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-[#0A2540] border-t-transparent" />
          <p className="text-sm font-medium text-gray-500">Loading partner status...</p>
        </div>
      </div>
    );
  }

  if (fetchError || !statusData) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-12">
        <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center text-red-700">
          <p className="font-semibold">{fetchError || 'Unable to retrieve status'}</p>
          <button
            onClick={fetchStatus}
            className="mt-4 rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-red-800"
          >
            Try Again
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <div className="mb-8">
        <h1 className="text-3xl font-extrabold text-[#0A2540]">Partner Application Status</h1>
        <p className="mt-1 text-sm text-gray-600">
          Track the progress and review state of your partner account.
        </p>
      </div>

      <OnboardingStatusBanner
        statusData={statusData}
        onStatusUpdated={(updated) => setStatusData(updated)}
      />
    </div>
  );
}
