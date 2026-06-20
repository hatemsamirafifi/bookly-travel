import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { VerifyEmailClient } from '@/components/auth/VerifyEmailClient';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.travel';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const resolved = await params;
  const locale = resolved?.locale ?? 'en';
  const canonicalUrl = `${SITE_URL}/${locale}/auth/verify-email`;
  const t = await getTranslations({ locale, namespace: 'auth.verifyEmail' });

  return {
    title: t('metaTitle'),
    description: t('metaDescription'),
    alternates: {
      canonical: canonicalUrl,
    },
    openGraph: {
      title: t('metaTitle'),
      description: t('metaDescription'),
      url: canonicalUrl,
      siteName: 'Bookly',
      type: 'website',
    },
  };
}

interface VerifyEmailPageProps {
  searchParams: Promise<{ status?: string | string[]; email?: string | string[] }>;
}

function normalizeSearchParam(value: string | string[] | undefined): string | undefined {
  if (value === undefined) return undefined;
  if (Array.isArray(value)) return value[0];
  return value;
}

export default async function VerifyEmailPage({ searchParams }: VerifyEmailPageProps) {
  const t = await getTranslations('auth.verifyEmail');
  const params = await searchParams;
  const status = normalizeSearchParam(params.status);

  return (
    <div className="flex min-h-[100dvh] items-center justify-center p-6 bg-gradient-to-br from-background to-[color-mix(in_srgb,var(--color-primary)_6%,var(--background))]">
      <div className="w-full max-w-[26rem] bg-surface border border-border rounded-lg shadow-card px-8 py-10">
        {/* Header */}
        <header className="mb-8 text-center">
          <p className="text-2xl font-extrabold text-primary tracking-tight mb-4">Bookly</p>
          <h1 className="text-[1.375rem] font-bold text-foreground tracking-tight m-0 mb-1.5">{t('title')}</h1>
        </header>

        <VerifyEmailClient status={status} />
      </div>
    </div>
  );
}
