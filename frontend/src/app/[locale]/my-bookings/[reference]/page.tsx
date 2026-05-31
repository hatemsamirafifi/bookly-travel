import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { AuthGuard } from '@/components/auth/AuthGuard';
import BookingDetailClient from './client';

interface Props {
  params: Promise<{ locale: string; reference: string }>;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale, reference } = await params;
  const t = await getTranslations({ locale, namespace: 'traveler.pages.bookingDetail' });

  return {
    title: t('metaTitle', { reference }),
  };
}

export default async function BookingDetailPage({ params }: Props) {
  const { locale, reference } = await params;
  const t = await getTranslations({ locale, namespace: 'traveler.pages.bookingDetail' });

  return (
    <AuthGuard>
      <main className="mx-auto max-w-4xl px-4 py-8 sm:py-12">
        <h1 className="mb-6 text-2xl font-bold text-gray-900">{t('title')}</h1>
        <BookingDetailClient reference={reference} locale={locale} />
      </main>
    </AuthGuard>
  );
}
