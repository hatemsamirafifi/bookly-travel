import type { Metadata } from 'next';
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

  let data;
  try {
    data = await getHomepageData(locale);
  } catch {
    return (
      <main className="min-h-screen">
        <HeroSection locale={locale} />
        <div className="py-12 text-center text-gray-500">
          Unable to load homepage content. Please try again later.
        </div>
      </main>
    );
  }

  return (
    <>
      <OrganizationSchema locale={locale} />
      <HeroSection locale={locale} />
      <FeaturedTours tours={data.featured_tours} locale={locale} />
      <CategoryGrid categories={data.popular_categories} locale={locale} />
      <DestinationShowcase destinations={data.featured_destinations} locale={locale} />
    </>
  );
}
