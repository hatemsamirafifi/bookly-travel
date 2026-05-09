import type { Metadata } from 'next';
import BookingForm from '@/components/booking/BookingForm';

interface Props {
  params: Promise<{ locale: string }>;
  searchParams: Promise<{ tour?: string; date?: string; participants?: string }>;
}

export async function generateMetadata({ params, searchParams }: Props): Promise<Metadata> {
  const { locale } = await params;
  const sp = await searchParams;

  const titles: Record<string, string> = {
    en: `Book ${sp.tour || 'a Tour'} | Bookly`,
    es: `Reservar ${sp.tour || 'un Tour'} | Bookly`,
    it: `Prenota ${sp.tour || 'un Tour'} | Bookly`,
  };

  const descriptions: Record<string, string> = {
    en: 'Complete your booking. Instant confirmation with secure payment processing.',
    es: 'Complete su reserva. Confirmación instantánea con procesamiento de pago seguro.',
    it: 'Completa la tua prenotazione. Conferma immediata con elaborazione del pagamento sicura.',
  };

  return {
    title: titles[locale] || titles.en,
    description: descriptions[locale] || descriptions.en,
  };
}

export default async function BookingPage({ params, searchParams }: Props) {
  const { locale } = await params;
  const sp = await searchParams;

  return (
    <main className="mx-auto max-w-lg px-4 py-8 sm:py-12">
      <h1 className="text-2xl font-bold text-gray-900 mb-6">
        {locale === 'es' ? 'Completar Reserva' : locale === 'it' ? 'Completa Prenotazione' : 'Complete Your Booking'}
      </h1>
      <BookingForm locale={locale} />
    </main>
  );
}
