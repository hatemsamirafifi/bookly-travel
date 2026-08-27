import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { getHomepageData } from '@/lib/api/homepage';
import { OrganizationSchema } from '@/components/seo/StructuredData';
import HeroSection from '@/components/home/HeroSection';
import FeaturedTours from '@/components/home/FeaturedTours';
import CategoryGrid from '@/components/home/CategoryGrid';
import DestinationShowcase from '@/components/home/DestinationShowcase';

interface HomePageProps {
  params: Promise<{ locale: string }>;
}

export async function generateMetadata({ params }: HomePageProps): Promise<Metadata> {
  const { locale } = await params;

  try {
    const data = await getHomepageData(locale);
    return {
      title: data.meta.seo.meta_title,
      description: data.meta.seo.meta_description,
      openGraph: {
        title: data.meta.seo.meta_title,
        description: data.meta.seo.meta_description,
        type: 'website',
      },
    };
  } catch {
    return {
      title: 'Bookly — Discover & Book Amazing Tours',
      description: 'Discover and instantly book the best tours worldwide.',
    };
  }
}

export default async function HomePage({ params }: HomePageProps) {
  const { locale } = await params;
  const t = await getTranslations('home');

  let data;
  try {
    data = await getHomepageData(locale);
  } catch {
    return (
      <div className="min-h-screen">
        <HeroSection title={t('heroTitle')} subtitle={t('heroSubtitle')} />
        <div className="py-12 text-center text-gray-500">
          Unable to load homepage content. Please try again later.
        </div>
      </div>
    );
  }

  // The homepage response is wrapped in a `data` envelope
  // (category-destination-api.md:128-130); the featured tours, categories,
  // and destinations live under data.data, SEO under data.meta.
  const home = data.data;

  return (
    <>
      <OrganizationSchema locale={locale} />
      <HeroSection title={t('heroTitle')} subtitle={t('heroSubtitle')} />
      <FeaturedTours tours={home.featured_tours} locale={locale} />
      <CategoryGrid categories={home.popular_categories} locale={locale} />
      <DestinationShowcase destinations={home.featured_destinations} locale={locale} />
    </>
  );
}
