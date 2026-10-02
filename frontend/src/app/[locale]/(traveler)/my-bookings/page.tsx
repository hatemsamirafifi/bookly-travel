import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import BookingList from '@/components/my-bookings/BookingList';
import TravelerPageShell from '@/components/traveler/TravelerPageShell';

interface Props {
  params: Promise<{ locale: string }>;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'traveler.pages.myBookings' });

  return {
    title: t('metaTitle'),
    description: t('metaDescription'),
  };
}

export default async function MyBookingsPage({ params }: Props) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'traveler.pages.myBookings' });

  return (
    <TravelerPageShell title={t('title')} subtitle={t('subtitle')}>
      <BookingList locale={locale} />
    </TravelerPageShell>
  );
}
