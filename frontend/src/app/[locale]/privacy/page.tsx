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
    <main className="mx-auto max-w-3xl px-4 py-12 sm:py-16">
      <h1 className="text-3xl font-bold text-[#0A2540] mb-8">{t('title')}</h1>
      <div className="prose prose-slate max-w-none text-[#0A2540]/80">
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
    </main>
  );
}
