import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import { getHomepageData } from '@/lib/api/homepage';
import { getBlogPosts } from '@/lib/api/blog';
import { OrganizationSchema } from '@/components/seo/StructuredData';
import HeroSection from '@/components/home/HeroSection';
import CategoryChips from '@/components/home/CategoryChips';
import BooklyValuePanel from '@/components/home/BooklyValuePanel';
import CategoryGrid from '@/components/home/CategoryGrid';
import FeaturedTours from '@/components/home/FeaturedTours';
import DestinationShowcase from '@/components/home/DestinationShowcase';
import ThemedTourRails from '@/components/home/ThemedTourRails';
import BlogInspiration from '@/components/home/BlogInspiration';
import PartnerAcquisitionPanel from '@/components/home/PartnerAcquisitionPanel';
import CatalogRecoveryAlert from '@/components/home/CatalogRecoveryAlert';
import HomeErrorBoundary from '@/components/home/HomeErrorBoundary';

interface HomePageProps {
  params: Promise<{ locale: string }>;
}

export async function generateMetadata({ params }: HomePageProps): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'home' });

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
      title: `${t('heroTitle')} | Bookly`,
      description: t('heroSubtitle'),
    };
  }
}

export default async function HomePage({ params }: HomePageProps) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'home' });

  const [homeResult, blogResult] = await Promise.allSettled([
    getHomepageData(locale),
    getBlogPosts(locale, { per_page: 3 }),
  ]);

  if (homeResult.status === 'rejected') {
    return (
      <div className="min-h-screen">
        <HeroSection title={t('heroTitle')} subtitle={t('heroSubtitle')} />
        <CatalogRecoveryAlert />
      </div>
    );
  }

  // The homepage response envelope: tours, categories, destinations, rails under data.data
  const home = homeResult.value.data;
  const articles = blogResult.status === 'fulfilled' ? blogResult.value.data : [];

  return (
    <>
      <OrganizationSchema locale={locale} />
      {/* 1. "Where to?" Search Hero */}
      <HeroSection title={t('heroTitle')} subtitle={t('heroSubtitle')} />

      {/* 2. Tour-Category Chips */}
      {home.popular_categories && home.popular_categories.length > 0 && (
        <HomeErrorBoundary sectionName="CategoryChips">
          <CategoryChips categories={home.popular_categories} locale={locale} />
        </HomeErrorBoundary>
      )}

      {/* 3. Bookly Value Panel */}
      <HomeErrorBoundary sectionName="BooklyValuePanel">
        <BooklyValuePanel locale={locale} />
      </HomeErrorBoundary>

      {/* 4. Popular Categories Grid */}
      <HomeErrorBoundary sectionName="CategoryGrid">
        <CategoryGrid categories={home.popular_categories} locale={locale} />
      </HomeErrorBoundary>

      {/* 5. Featured Tours Snap Rail */}
      <HomeErrorBoundary sectionName="FeaturedTours">
        <FeaturedTours tours={home.featured_tours} locale={locale} />
      </HomeErrorBoundary>

      {/* 6. Top Destinations Showcase */}
      <HomeErrorBoundary sectionName="DestinationShowcase">
        <DestinationShowcase destinations={home.featured_destinations} locale={locale} />
      </HomeErrorBoundary>

      {/* 7. Themed / Seasonal Curated Rails */}
      {home.curated_rails && home.curated_rails.length > 0 && (
        <HomeErrorBoundary sectionName="ThemedTourRails">
          <ThemedTourRails rails={home.curated_rails} locale={locale} />
        </HomeErrorBoundary>
      )}

      {/* 8. Blog Inspiration Articles */}
      <HomeErrorBoundary sectionName="BlogInspiration">
        <BlogInspiration articles={articles} locale={locale} />
      </HomeErrorBoundary>

      {/* 9. Partner Acquisition Invitation */}
      <HomeErrorBoundary sectionName="PartnerAcquisitionPanel">
        <PartnerAcquisitionPanel locale={locale} />
      </HomeErrorBoundary>
    </>
  );
}
