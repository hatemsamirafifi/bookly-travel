import { getTranslations } from 'next-intl/server';
import type { Metadata } from 'next';
import PartnerReviewsDashboard from '@/components/partner/reviews/PartnerReviewsDashboard';

interface Props {
  params: Promise<{ locale: string }>;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'partner.reviews' });
  return {
    title: t('metaTitle'),
    description: t('metaDescription'),
  };
}

export default async function ReviewsPage({ params }: Props) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'partner.reviews' });

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-[#0A2540]">{t('title')}</h1>
        <p className="mt-1 text-sm text-gray-500">{t('metaDescription')}</p>
      </div>
      <PartnerReviewsDashboard />
    </div>
  );
}