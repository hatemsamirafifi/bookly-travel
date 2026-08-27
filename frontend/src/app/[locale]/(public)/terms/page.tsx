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
    <div className="mx-auto max-w-3xl px-4 py-12 sm:py-16">
      <h1 className="text-3xl font-bold text-[#0A2540] mb-8">{t('title')}</h1>
      <div className="prose prose-slate max-w-none text-[#0A2540]/80">
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
  );
}
