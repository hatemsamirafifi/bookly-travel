import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { fetchPublicStaticPage } from '@/lib/api/pages';
import { sanitizeHtml } from '@/lib/sanitizeHtml';
import type { PublicStaticPage } from '@/lib/api/types';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'legal' });
  const cmsPage = await fetchPublicStaticPage('privacy', locale);

  if (cmsPage) {
    return {
      title: cmsPage.meta_title ?? cmsPage.title,
      description: cmsPage.meta_description ?? t('privacy.metaDescription'),
    };
  }

  return {
    title: t('privacy.metaTitle'),
    description: t('privacy.metaDescription'),
  };
}

export default async function PrivacyPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'legal.privacy' });
  // CMS-managed content first (Admin 2.0): fall back gracefully to the
  // next-intl legal strings when no published page exists or the CMS API is
  // unreachable.
  const cmsPage: PublicStaticPage | null = await fetchPublicStaticPage('privacy', locale);

  return (
    <main className="min-h-screen bg-surface-alt py-10 sm:py-16">
      <div className="mx-auto max-w-3xl px-4 sm:px-6">
        <h1 className="mb-8 text-3xl font-bold text-bookly-navy sm:text-4xl">{cmsPage?.title ?? t('title')}</h1>

        {cmsPage?.translation_warning && (
          <div className="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
            {cmsPage.translation_warning}
          </div>
        )}

        {cmsPage ? (
          <div
            className="prose prose-slate max-w-none rounded-xl border border-border bg-surface p-5 text-bookly-navy/80 shadow-sm sm:p-8"
            dangerouslySetInnerHTML={{ __html: sanitizeHtml(cmsPage.body) }}
          />
        ) : (
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
        )}
      </div>
    </main>
  );
}
