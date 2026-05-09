import type { Metadata } from 'next';
import BookingList from '@/components/my-bookings/BookingList';

interface Props {
  params: Promise<{ locale: string }>;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;

  const titles: Record<string, string> = {
    en: 'My Bookings | Bookly',
    es: 'Mis Reservas | Bookly',
    it: 'Le Mie Prenotazioni | Bookly',
  };

  const descriptions: Record<string, string> = {
    en: 'View and manage your tour bookings.',
    es: 'Vea y gestione sus reservas de tours.',
    it: 'Visualizza e gestisci le tue prenotazioni di tour.',
  };

  return {
    title: titles[locale] || titles.en,
    description: descriptions[locale] || descriptions.en,
  };
}

export default async function MyBookingsPage({ params }: Props) {
  const { locale } = await params;

  return (
    <main className="mx-auto max-w-2xl px-4 py-8 sm:py-12">
      <h1 className="text-2xl font-bold text-gray-900 mb-6">
        {locale === 'es' ? 'Mis Reservas' : locale === 'it' ? 'Le Mie Prenotazioni' : 'My Bookings'}
      </h1>
      <BookingList locale={locale} />
    </main>
  );
}
