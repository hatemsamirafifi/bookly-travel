import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { ResetPasswordForm } from '@/components/auth/ResetPasswordForm';
import { AuthPageShell } from '@/components/auth/AuthPageShell';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.travel';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const resolved = await params;
  const locale = resolved?.locale ?? 'en';
  const canonicalUrl = `${SITE_URL}/${locale}/auth/reset-password`;
  const t = await getTranslations({ locale, namespace: 'auth.resetPassword' });

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

interface ResetPasswordPageProps {
  searchParams: Promise<{ token?: string | string[]; email?: string | string[] }>;
}

function normalizeSearchParam(value: string | string[] | undefined): string | undefined {
  if (value === undefined) return undefined;
  if (Array.isArray(value)) return value[0];
  return value;
}

export default async function ResetPasswordPage({ searchParams }: ResetPasswordPageProps) {
  const t = await getTranslations('auth');
  const params = await searchParams;
  const token = normalizeSearchParam(params.token);
  const email = normalizeSearchParam(params.email);

  return (
    <AuthPageShell title={t('resetPassword.title')} subtitle={t('resetPassword.subtitle')}>
      {token && email ? (
          <ResetPasswordForm token={token} email={email} />
        ) : (
          <div className="rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm text-error" role="alert" aria-live="assertive">
            {t('errors.invalidToken')}
          </div>
        )}
    </AuthPageShell>
  );
}
