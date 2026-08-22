import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { PartnerRegistrationForm } from '@/components/partner/PartnerRegistrationForm';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.travel';

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const resolved = await params;
  const locale = resolved?.locale ?? 'en';
  const canonicalUrl = `${SITE_URL}/${locale}/partner-register`;
  const t = await getTranslations({ locale, namespace: 'partnerOnboarding.register' });

  return {
    title: `${t('title')} | Bookly`,
    description: t('subtitle'),
    alternates: {
      canonical: canonicalUrl,
    },
    openGraph: {
      title: `${t('title')} | Bookly`,
      description: t('subtitle'),
      url: canonicalUrl,
      siteName: 'Bookly',
      type: 'website',
    },
  };
}

export default async function PartnerRegisterPage() {
  const t = await getTranslations('partnerOnboarding.register');

  return (
    <div className="flex min-h-[100dvh] items-center justify-center p-6 bg-gradient-to-br from-background to-[color-mix(in_srgb,var(--color-primary)_6%,var(--background))]">
      <div className="w-full max-w-3xl bg-surface border border-border rounded-xl shadow-card px-8 py-10 my-8">
        <header className="mb-8 text-center">
          <p className="text-2xl font-extrabold text-primary tracking-tight mb-2">Bookly Partners</p>
          <h1 className="text-2xl sm:text-3xl font-bold text-foreground tracking-tight m-0 mb-2">{t('title')}</h1>
          <p className="text-sm text-text-muted m-0">{t('subtitle')}</p>
        </header>

        <PartnerRegistrationForm />
      </div>
    </div>
  );
}
