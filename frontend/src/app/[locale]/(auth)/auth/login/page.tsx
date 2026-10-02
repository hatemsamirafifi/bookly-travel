import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { LoginForm } from '@/components/auth/LoginForm';
import { AuthPageShell } from '@/components/auth/AuthPageShell';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.travel';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const resolved = await params;
  const locale = resolved?.locale ?? 'en';
  const canonicalUrl = `${SITE_URL}/${locale}/auth/login`;
  const t = await getTranslations({ locale, namespace: 'auth.signin' });

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


interface LoginPageProps {
  searchParams: Promise<{ returnUrl?: string | string[]; sessionExpired?: string | string[] }>;
}

function normalizeSearchParam(value: string | string[] | undefined): string | undefined {
  if (value === undefined) return undefined;
  if (Array.isArray(value)) return value[0];
  return value;
}

export default async function LoginPage({ searchParams }: LoginPageProps) {
  const t = await getTranslations('auth.signin');
  const params = await searchParams;
  const returnUrl = normalizeSearchParam(params.returnUrl);
  const sessionExpired = normalizeSearchParam(params.sessionExpired);

  return (
    <AuthPageShell title={t('title')} subtitle={t('subtitle')}>
      <LoginForm returnUrl={returnUrl} sessionExpired={sessionExpired === '1'} />
    </AuthPageShell>
  );
}
