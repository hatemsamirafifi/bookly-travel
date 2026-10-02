import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { ForgotPasswordForm } from '@/components/auth/ForgotPasswordForm';
import { AuthPageShell } from '@/components/auth/AuthPageShell';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.travel';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const resolved = await params;
  const locale = resolved?.locale ?? 'en';
  const canonicalUrl = `${SITE_URL}/${locale}/auth/forgot-password`;
  const t = await getTranslations({ locale, namespace: 'auth.forgotPassword' });

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

export default async function ForgotPasswordPage() {
  const t = await getTranslations('auth.forgotPassword');

  return (
    <AuthPageShell title={t('title')} subtitle={t('subtitle')}>
      <ForgotPasswordForm />
    </AuthPageShell>
  );
}
