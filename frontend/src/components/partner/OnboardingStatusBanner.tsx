'use client';

import React, { useState } from 'react';
import { useTranslations, useLocale } from 'next-intl';
import Link from 'next/link';
import type { PartnerOnboardingStatus } from '@/types/partner';
import { ResubmissionForm } from './ResubmissionForm';

interface OnboardingStatusBannerProps {
  statusData: PartnerOnboardingStatus;
  onStatusUpdated?: (newStatus: PartnerOnboardingStatus) => void;
}

export function OnboardingStatusBanner({
  statusData,
  onStatusUpdated,
}: OnboardingStatusBannerProps) {
  const t = useTranslations('partnerOnboarding.status');
  const tResubmit = useTranslations('partnerOnboarding.resubmit');
  const locale = useLocale();

  const [showResubmitForm, setShowResubmitForm] = useState(false);

  const { onboarding_status, rejection_reason, suspension_reason } = statusData;

  if (onboarding_status === 'approved') {
    return (
      <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-emerald-900 shadow-sm" role="status">
        <div className="flex items-start gap-4">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 font-bold text-lg">
            ✓
          </div>
          <div className="flex-1">
            <h3 className="text-lg font-bold text-emerald-950">{t('approvedTitle')}</h3>
            <p className="mt-1 text-sm text-emerald-800">{t('approvedDescription')}</p>
            <div className="mt-4 flex flex-wrap gap-3">
              <Link
                href={`/${locale}/partner/dashboard`}
                className="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-emerald-700"
              >
                {t('dashboardButton')} &rarr;
              </Link>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (onboarding_status === 'rejected') {
    return (
      <div className="space-y-6">
        <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-red-900 shadow-sm" role="alert">
          <div className="flex items-start gap-4">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 font-bold text-lg">
              ✕
            </div>
            <div className="flex-1">
              <h3 className="text-lg font-bold text-red-950">{t('rejectedTitle')}</h3>
              <p className="mt-1 text-sm text-red-800">{t('rejectedDescription')}</p>

              {rejection_reason && (
                <div className="mt-4 rounded-lg border border-red-200 bg-white/70 p-4">
                  <span className="block text-xs font-bold uppercase tracking-wider text-red-700">
                    {t('rejectionReasonLabel')}:
                  </span>
                  <p className="mt-1 text-sm text-gray-800 whitespace-pre-line">
                    {rejection_reason}
                  </p>
                </div>
              )}

              <div className="mt-4">
                <button
                  type="button"
                  onClick={() => setShowResubmitForm(!showResubmitForm)}
                  className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow transition hover:bg-red-800"
                >
                  {showResubmitForm ? tResubmit('cancelButton') : t('resubmitButton')}
                </button>
              </div>
            </div>
          </div>
        </div>

        {showResubmitForm && (
          <ResubmissionForm
            onSuccess={(updated) => {
              setShowResubmitForm(false);
              if (onStatusUpdated) {
                onStatusUpdated(updated);
              }
            }}
            onCancel={() => setShowResubmitForm(false)}
          />
        )}
      </div>
    );
  }

  if (onboarding_status === 'suspended') {
    return (
      <div className="rounded-xl border border-amber-300 bg-amber-50 p-6 text-amber-950 shadow-sm" role="alert">
        <div className="flex items-start gap-4">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-200 text-amber-800 font-bold text-lg">
            !
          </div>
          <div className="flex-1">
            <h3 className="text-lg font-bold text-amber-950">{t('suspendedTitle')}</h3>
            <p className="mt-1 text-sm text-amber-900">{t('suspendedDescription')}</p>

            {suspension_reason && (
              <div className="mt-4 rounded-lg border border-amber-200 bg-white/70 p-4">
                <span className="block text-xs font-bold uppercase tracking-wider text-amber-800">
                  {t('suspensionReasonLabel')}:
                </span>
                <p className="mt-1 text-sm text-gray-800 whitespace-pre-line">
                  {suspension_reason}
                </p>
              </div>
            )}
          </div>
        </div>
      </div>
    );
  }

  // Pending / default
  return (
    <div className="rounded-xl border border-blue-200 bg-blue-50 p-6 text-blue-950 shadow-sm" role="status">
      <div className="flex items-start gap-4">
        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 font-bold text-lg animate-pulse">
          ⏳
        </div>
        <div className="flex-1">
          <h3 className="text-lg font-bold text-blue-950">{t('pendingTitle')}</h3>
          <p className="mt-1 text-sm text-blue-900">{t('pendingDescription')}</p>
          <div className="mt-4 flex items-center gap-2 text-xs font-medium text-blue-800 bg-blue-100/60 w-fit px-3 py-1.5 rounded-md">
            <span>ℹ</span>
            <span>{t('timelineNotice')}</span>
          </div>
        </div>
      </div>
    </div>
  );
}
