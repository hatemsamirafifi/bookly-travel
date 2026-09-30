import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'legal' });
  return {
    title: t('terms.metaTitle'),
    description: t('terms.metaDescription'),
  };
}

export default async function TermsPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'legal.terms' });

  return (
    <main className="min-h-screen bg-surface-alt py-10 sm:py-16">
      <div className="mx-auto max-w-3xl px-4 sm:px-6">
      <h1 className="mb-8 text-3xl font-bold text-bookly-navy sm:text-4xl">{t('title')}</h1>
      <div className="prose prose-slate max-w-none rounded-xl border border-border bg-surface p-5 text-bookly-navy/80 shadow-sm sm:p-8">
        <p className="mb-4">{t('intro')}</p>
        <h2 className="text-xl font-semibold text-[#0A2540] mt-8 mb-4">{t('bookings.title')}</h2>
        <p className="mb-4">{t('bookings.body')}</p>
        <h2 className="text-xl font-semibold text-[#0A2540] mt-8 mb-4">{t('payments.title')}</h2>
        <p className="mb-4">{t('payments.body')}</p>
        <h2 className="text-xl font-semibold text-[#0A2540] mt-8 mb-4">{t('cancellations.title')}</h2>
        <p className="mb-4">{t('cancellations.body')}</p>
        <h2 className="text-xl font-semibold text-[#0A2540] mt-8 mb-4">{t('liability.title')}</h2>
        <p className="mb-4">{t('liability.body')}</p>
        <h2 className="text-xl font-semibold text-[#0A2540] mt-8 mb-4">{t('contact.title')}</h2>
        <p className="mb-4">{t('contact.body')}</p>
      </div>
      </div>
    </main>
  );
}
