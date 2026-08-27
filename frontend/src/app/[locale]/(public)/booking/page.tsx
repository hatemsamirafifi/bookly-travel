import type { Metadata } from 'next';
import { Suspense } from 'react';
import { getTranslations } from 'next-intl/server';
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
    it: 'Completa la tua prenotazione. Conferma immediata con elaborazione del pagamento sicuro.',
  };

  return {
    title: titles[locale] || titles.en,
    description: descriptions[locale] || descriptions.en,
  };
}

export default async function BookingPage({ params }: Props) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'booking' });

  return (
    <div className="mx-auto max-w-lg px-4 py-8 sm:py-12">
      <h1 className="text-2xl font-bold text-[#0A2540] mb-6">{t('title')}</h1>
      {/* F15: BookingForm calls useSearchParams() and must be wrapped in a
          Suspense boundary so the route renders during static generation. */}
      <Suspense fallback={<div className="animate-pulse space-y-6">
        <div className="h-16 bg-gray-100 rounded-lg" />
        <div className="h-32 bg-gray-100 rounded-lg" />
        <div className="h-24 bg-gray-100 rounded-lg" />
      </div>}>
        <BookingForm locale={locale} />
      </Suspense>
    </div>
  );
}