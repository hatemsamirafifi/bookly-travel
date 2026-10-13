import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { TourWizard } from '@/components/partner/tours/TourWizard';

interface Props {
  params: Promise<{ locale: string }>;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'partner.tours' });
  return {
    title: t('createTour'),
    description: t('metaDescription'),
  };
}

export default function CreateTourPage() {
  return <TourWizard />;
}