import React from 'react';
import type { Metadata } from 'next';
import Link from 'next/link';
import { getTranslations } from 'next-intl/server';
import { validateInviteToken } from '@/lib/api/partner';
import { PartnerInviteForm } from '@/components/partner/PartnerInviteForm';

export const metadata: Metadata = {
  robots: {
    index: false,
    follow: false,
  },
};

interface PartnerInvitePageProps {
  params: Promise<{ locale: string; token: string }>;
}

export default async function PartnerInvitePage({ params }: PartnerInvitePageProps) {
  const { locale, token } = await params;
  const t = await getTranslations({ locale, namespace: 'partnerOnboarding.invite' });

  let invitationData = null;
  let errorState = null;

  try {
    const response = await validateInviteToken(token);
    invitationData = response.data;
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : (err as { message?: string })?.message;
    errorState = message || t('invalidMessage');
  }

  if (errorState || !invitationData) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center p-6 bg-gradient-to-br from-background to-[color-mix(in_srgb,var(--color-primary)_6%,var(--background))]">
        <div className="w-full max-w-lg bg-surface border border-border rounded-xl shadow-card p-8 text-center space-y-6">
          <div className="w-16 h-16 bg-red-100 dark:bg-red-950/50 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center mx-auto">
            <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <div>
            <h1 className="text-2xl font-bold text-foreground mb-2">{t('invalidTitle')}</h1>
            <p className="text-sm text-text-muted">{t('invalidMessage')}</p>
          </div>
          <div className="pt-4 flex flex-col sm:flex-row gap-3 justify-center">
            <Link
              href={`/${locale}/partner-register`}
              className="px-5 py-2.5 bg-primary text-primary-foreground text-sm font-semibold rounded-lg hover:bg-primary/90 transition-colors"
            >
              Standard Registration
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-[100dvh] items-center justify-center p-6 bg-gradient-to-br from-background to-[color-mix(in_srgb,var(--color-primary)_6%,var(--background))]">
      <div className="w-full max-w-3xl bg-surface border border-border rounded-xl shadow-card px-8 py-10 my-8">
        <header className="mb-8 text-center">
          <p className="text-2xl font-extrabold text-primary tracking-tight mb-2">Bookly Partners</p>
          <h1 className="text-2xl sm:text-3xl font-bold text-foreground tracking-tight m-0 mb-2">{t('title')}</h1>
          <p className="text-sm text-text-muted m-0">{t('subtitle')}</p>
        </header>

        <PartnerInviteForm token={token} invitation={invitationData} />
      </div>
    </div>
  );
}