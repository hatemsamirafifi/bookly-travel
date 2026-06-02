import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { AuthGuard } from '@/components/auth/AuthGuard';
import MyReviewsList from '@/components/reviews/MyReviewsList';

interface Props {
  params: Promise<{ locale: string }>;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'traveler.pages.myReviews' });
  return {
    title: t('metaTitle'),
    description: t('metaDescription'),
  };
}

export default async function MyReviewsPage({ params }: Props) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'traveler.pages.myReviews' });

  return (
    <AuthGuard>
      <main className="mx-auto max-w-4xl px-4 py-8 sm:py-12">
        <h1 className="mb-2 text-2xl font-bold text-gray-900">{t('title')}</h1>
        <p className="mb-6 text-sm text-gray-600">{t('subtitle')}</p>
        <MyReviewsList locale={locale} />
      </main>
    </AuthGuard>
  );
}
