import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'legal' });
  return {
    title: t('privacy.metaTitle'),
    description: t('privacy.metaDescription'),
  };
}

export default async function PrivacyPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'legal.privacy' });

  return (
    <main className="min-h-screen bg-surface-alt py-10 sm:py-16">
      <div className="mx-auto max-w-3xl px-4 sm:px-6">
      <h1 className="mb-8 text-3xl font-bold text-bookly-navy sm:text-4xl">{t('title')}</h1>
      <div className="prose prose-slate max-w-none rounded-xl border border-border bg-surface p-5 text-bookly-navy/80 shadow-sm sm:p-8">
        <p className="mb-4">{t('intro')}</p>
        <h2 className="text-xl font-semibold text-[#0A2540] mt-8 mb-4">{t('dataCollection.title')}</h2>
        <p className="mb-4">{t('dataCollection.body')}</p>
        <h2 className="text-xl font-semibold text-[#0A2540] mt-8 mb-4">{t('cookies.title')}</h2>
        <p className="mb-4">{t('cookies.body')}</p>
        <h2 className="text-xl font-semibold text-[#0A2540] mt-8 mb-4">{t('thirdParties.title')}</h2>
        <p className="mb-4">{t('thirdParties.body')}</p>
        <h2 className="text-xl font-semibold text-[#0A2540] mt-8 mb-4">{t('rights.title')}</h2>
        <p className="mb-4">{t('rights.body')}</p>
        <h2 className="text-xl font-semibold text-[#0A2540] mt-8 mb-4">{t('contact.title')}</h2>
        <p className="mb-4">{t('contact.body')}</p>
      </div>
      </div>
    </main>
  );
}
