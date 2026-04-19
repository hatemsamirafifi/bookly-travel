import type { Metadata } from 'next';
import { useTranslations } from 'next-intl';
import { AuthGuard } from '@/components/auth/AuthGuard';
import { RegisterForm } from '@/components/auth/RegisterForm';

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.travel';

export function generateMetadata({ params }: { params: { locale: string } }): Metadata {
  const locale = params?.locale ?? 'en';
  const canonicalUrl = `${SITE_URL}/${locale}/auth/register`;

  return {
    title: 'Create Account | Bookly',
    description:
      'Create your Bookly traveler account. Join thousands discovering tours worldwide — registration is free and takes under a minute.',
    alternates: {
      canonical: canonicalUrl,
    },
    openGraph: {
      title: 'Create Account | Bookly',
      description:
        'Join Bookly and discover tours across the world. Create your free traveler account in under a minute.',
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
  const t = useTranslations('auth');
  const { returnUrl } = await searchParams;

  return (
    <div className="auth-page">
      <div className="auth-card">
        {/* Header */}
        <header className="auth-card__header">
          <p className="auth-card__brand">Bookly</p>
          <h1 className="auth-card__title">{t('register.title')}</h1>
          <p className="auth-card__subtitle">{t('register.subtitle')}</p>
        </header>

        {/* Guest-only guard: redirect authenticated users away */}
        <AuthGuard requireAuth={false}>
          <RegisterForm returnUrl={returnUrl} />
        </AuthGuard>
      </div>
    </div>
  );
}
