import type { Metadata } from 'next';
import Link from 'next/link';
import { getTranslations } from 'next-intl/server';
import { getHomepageData } from '@/lib/api/homepage';
import { getBlogPosts } from '@/lib/api/blog';
import BlogCard from '@/components/blog/BlogCard';
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
        <div className="py-12 text-center text-text-muted" role="alert">
          {t('loadError')}
        </div>
      </div>
    );
  }

  // The homepage response is wrapped in a `data` envelope
  // (category-destination-api.md:128-130); the featured tours, categories,
  // and destinations live under data.data, SEO under data.meta.
  const home = homeResult.value.data;
  const articles = blogResult.status === 'fulfilled' ? blogResult.value.data : [];

  return (
    <>
      <OrganizationSchema locale={locale} />
      <HeroSection title={t('heroTitle')} subtitle={t('heroSubtitle')} />
      <FeaturedTours tours={home.featured_tours} locale={locale} />
      <CategoryGrid categories={home.popular_categories} locale={locale} />
      <DestinationShowcase destinations={home.featured_destinations} locale={locale} />
      {articles.length > 0 && (
        <section className="bg-surface py-12" aria-labelledby="home-editorial-title">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div className="mb-8 flex items-center justify-between gap-4">
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-text-muted">{t('editorialEyebrow')}</p>
                <h2 id="home-editorial-title" className="text-2xl font-bold text-bookly-navy">{t('editorialTitle')}</h2>
              </div>
              <Link href={`/${locale}/blog`} className="text-sm font-semibold text-bookly-navy underline-offset-4 hover:underline focus-visible:underline">
                {t('viewAllArticles')}
              </Link>
            </div>
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {articles.map((post) => <BlogCard key={post.id} post={post} locale={locale} />)}
            </div>
          </div>
        </section>
      )}
      <section className="border-t border-border bg-surface-alt py-12" aria-labelledby="home-partner-title">
        <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
          <div className="max-w-2xl">
            <p className="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-text-muted">{t('partnerEyebrow')}</p>
            <h2 id="home-partner-title" className="text-2xl font-bold text-bookly-navy">{t('partnerTitle')}</h2>
            <p className="mt-2 text-text-muted">{t('partnerDescription')}</p>
          </div>
          <Link href={`/${locale}/partner-register`} className="inline-flex min-h-11 items-center justify-center self-start rounded-xl border border-bookly-navy px-5 py-3 text-sm font-semibold text-bookly-navy transition-colors hover:bg-bookly-navy hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">
            {t('partnerAction')}
          </Link>
        </div>
      </section>
    </>
  );
}
