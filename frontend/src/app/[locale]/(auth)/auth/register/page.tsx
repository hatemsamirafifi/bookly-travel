import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { RegisterForm } from '@/components/auth/RegisterForm';
import { AuthPageShell } from '@/components/auth/AuthPageShell';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.travel';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const resolved = await params;
  const locale = resolved?.locale ?? 'en';
  const canonicalUrl = `${SITE_URL}/${locale}/auth/register`;
  const t = await getTranslations({ locale, namespace: 'auth.register' });

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


interface RegisterPageProps {
  searchParams: Promise<{ returnUrl?: string }>;
}

export default async function RegisterPage({ searchParams }: RegisterPageProps) {
  const t = await getTranslations('auth.register');
  const { returnUrl } = await searchParams;

  return (
    <AuthPageShell title={t('title')} subtitle={t('subtitle')}>
      <RegisterForm returnUrl={returnUrl} />
    </AuthPageShell>
  );
}
