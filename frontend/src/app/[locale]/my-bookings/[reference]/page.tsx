import type { Metadata } from 'next';
import BookingDetailClient from './client';

interface Props {
  params: Promise<{ locale: string; reference: string }>;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale, reference } = await params;

  const titles: Record<string, string> = {
    en: `Booking ${reference} | Bookly`,
    es: `Reserva ${reference} | Bookly`,
    it: `Prenotazione ${reference} | Bookly`,
  };

  return {
    title: titles[locale] || titles.en,
  };
}

export default async function BookingDetailPage({ params }: Props) {
  const { locale, reference } = await params;

  return (
    <main className="mx-auto max-w-lg px-4 py-8 sm:py-12">
      <h1 className="text-2xl font-bold text-gray-900 mb-6">
        {locale === 'es' ? 'Detalle de Reserva' : locale === 'it' ? 'Dettaglio Prenotazione' : 'Booking Detail'}
      </h1>
      <BookingDetailClient reference={reference} locale={locale} />
    </main>
  );
}
