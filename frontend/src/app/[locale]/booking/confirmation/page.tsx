import type { Metadata } from 'next';
import BookingConfirmation from '@/components/booking/BookingConfirmation';

interface Props {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ ref?: string }>;
}

export async function generateMetadata({ params, searchParams }: Props): Promise<Metadata> {
  const { locale } = await params;
  const sp = await searchParams;

  const titles: Record<string, string> = {
    en: 'Booking Confirmed | Bookly',
    es: 'Reserva Confirmada | Bookly',
    it: 'Prenotazione Confermata | Bookly',
  };

  return {
    title: titles[locale] || titles.en,
  };
}

export default async function BookingConfirmationPage({ params, searchParams }: Props) {
  const { locale } = await params;
  const sp = await searchParams;
  const reference = sp.ref || '';

  return (
    <main className="mx-auto max-w-lg px-4 py-8 sm:py-12">
      <BookingConfirmation reference={reference} locale={locale} />
    </main>
  );
}
