import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { PartnerRegisterForm } from '@/components/auth/PartnerRegisterForm';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.travel';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const resolved = await params;
  const locale = resolved?.locale ?? 'en';
  const canonicalUrl = `${SITE_URL}/${locale}/auth/partner-register`;

  return {
    title: 'Become a Partner — Bookly Travel',
    description: 'Register as a partner to publish and manage your tours on the Bookly platform.',
    alternates: {
      canonical: canonicalUrl,
    },
    openGraph: {
      title: 'Become a Partner — Bookly Travel',
      description: 'Register as a partner to publish and manage your tours on the Bookly platform.',
      url: canonicalUrl,
      siteName: 'Bookly',
      type: 'website',
    },
  };
}

interface PartnerRegisterPageProps {
  searchParams: Promise<{ returnUrl?: string }>;
}

export default async function PartnerRegisterPage({ searchParams }: PartnerRegisterPageProps) {
  const { returnUrl } = await searchParams;

  return (
    <div className="flex min-h-[100dvh] items-center justify-center p-6 bg-gradient-to-br from-background to-[color-mix(in_srgb,var(--color-primary)_6%,var(--background))]">
      <div className="w-full max-w-[42rem] bg-surface border border-border rounded-lg shadow-card px-8 py-10 my-10">
        <header className="mb-8 text-center">
          <p className="text-2xl font-extrabold text-primary tracking-tight mb-4">Bookly for Partners</p>
          <h1 className="text-[1.375rem] font-bold text-foreground tracking-tight m-0 mb-1.5">Create your Partner Account</h1>
          <p className="text-sm text-text-muted m-0">Join us to offer your tours and activities to thousands of travelers.</p>
        </header>

        <PartnerRegisterForm returnUrl={returnUrl} />
      </div>
    </div>
  );
}
