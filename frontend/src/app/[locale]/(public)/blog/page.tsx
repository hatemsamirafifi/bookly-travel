import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getBlogPosts } from '@/lib/api/blog';
import BlogFeaturedHero from '@/components/blog/BlogFeaturedHero';
import BlogList from '@/components/blog/BlogList';
import { BlogUnavailable } from '@/components/blog/BlogUnavailable';
import EmptyState from '@/components/ui/EmptyState';
import { ItemListSchema } from '@/components/seo/StructuredData';

interface BlogPageProps {
  params: Promise<{
    locale: string;
  }>;
  searchParams: Promise<{
    category?: string;
    page?: string;
  }>;
}

export async function generateMetadata({
  params,
  searchParams,
}: BlogPageProps): Promise<Metadata> {
  const { locale } = await params;
  const { category, page } = await searchParams;

  const validLocales = ['en', 'es', 'it'];
  const activeLocale = validLocales.includes(locale) ? locale : 'en';

  const titles: Record<string, string> = {
    en: 'Travel Insights & Guides | Bookly Travel Blog',
    es: 'Guías y Consejos de Viaje | Blog de Bookly Travel',
    it: 'Guide e Consigli di Viaggio | Blog di Bookly Travel',
  };

  const descriptions: Record<string, string> = {
    en: 'Discover insider travel guides, curated itineraries, tips, and cultural highlights from our expert travel creators.',
    es: 'Descubre guías de viaje, itinerarios seleccionados y consejos culturales de nuestros creadores expertos.',
    it: 'Scopri guide di viaggio, itinerari selezionati e consigli culturali dai nostri creatori esperti.',
  };

  const title = titles[activeLocale] || titles.en;
  const description = descriptions[activeLocale] || descriptions.en;

  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || 'https://booklytravel.com';
  const queryStr = [
    category ? `category=${encodeURIComponent(category)}` : '',
    page && page !== '1' ? `page=${encodeURIComponent(page)}` : '',
  ]
    .filter(Boolean)
    .join('&');

  const canonicalPath = `/${activeLocale}/blog${queryStr ? `?${queryStr}` : ''}`;
  const canonicalUrl = `${baseUrl}${canonicalPath}`;

  return {
    title,
    description,
    alternates: {
      canonical: canonicalUrl,
      languages: {
        en: `${baseUrl}/en/blog${queryStr ? `?${queryStr}` : ''}`,
        es: `${baseUrl}/es/blog${queryStr ? `?${queryStr}` : ''}`,
        it: `${baseUrl}/it/blog${queryStr ? `?${queryStr}` : ''}`,
      },
    },
    openGraph: {
      title,
      description,
      url: canonicalUrl,
      type: 'website',
    },
  };
}

export default async function BlogIndexPage({
  params,
  searchParams,
}: BlogPageProps) {
  const { locale } = await params;
  const { category, page } = await searchParams;

  const validLocales = ['en', 'es', 'it'];
  if (!validLocales.includes(locale)) {
    notFound();
  }

  const currentPage = page ? parseInt(page, 10) : 1;
  const pageNum = isNaN(currentPage) || currentPage < 1 ? 1 : currentPage;

  let blogData;
  let isRateLimited = false;
  let retryAfter = 10;

  try {
    blogData = await getBlogPosts(locale, {
      category,
      page: pageNum,
      per_page: 12,
    });
  } catch (err: any) {
    if (err?.status === 429) {
      isRateLimited = true;
      retryAfter = err?.retryAfter ?? 10;
    } else {
      // WR-010: Don't mask server errors as "no articles" — render unavailable state
      return <BlogUnavailable status={err?.status || 500} />;
    }
  }

  if (isRateLimited) {
    return <BlogUnavailable status={429} retryAfterSeconds={retryAfter} />;
  }

  const { data: posts, meta } = blogData!;

  // Identify featured post for the hero if on page 1 and no category filter
  const featuredPost =
    pageNum === 1 && !category
      ? posts.find((p) => p.is_featured) || (posts.length > 0 ? posts[0] : null)
      : null;

  // Filter out the featured hero post from the grid if featured hero is rendered
  const gridPosts = featuredPost
    ? posts.filter((p) => p.id !== featuredPost.id)
    : posts;

  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || 'https://booklytravel.com';
  const itemListElements = posts.map((post, idx) => ({
    name: post.title,
    url: `${baseUrl}/${locale}/blog/${post.slug}`,
    position: (pageNum - 1) * 12 + idx + 1,
  }));

  return (
    <div className="min-h-screen bg-neutral-50 py-12">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Schema.org ItemList */}
        <ItemListSchema
          name="Bookly Travel Blog Articles"
          items={itemListElements.map(({ name, url }) => ({ name, url }))}
        />

        {/* Page Header */}
        <header className="mb-10 text-center sm:text-left">
          <p className="text-xs font-semibold uppercase tracking-wider text-primary-600 mb-2">
            Bookly Magazine
          </p>
          <h1 className="text-3xl sm:text-4xl font-extrabold text-neutral-900 tracking-tight">
            Travel Insights & Guides
          </h1>
          <p className="mt-3 max-w-2xl text-base text-neutral-600">
            Expert stories, destination breakdowns, and cultural guides crafted by local guides and seasoned globetrotters.
          </p>
        </header>

        {/* Featured Hero (Only on first page without category filter) */}
        {featuredPost && (
          <BlogFeaturedHero post={featuredPost} locale={locale} />
        )}

        {/* Blog Post Grid & Pagination or Empty State */}
        {posts.length > 0 ? (
          <div>
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-bold text-neutral-900">
                {category ? `Articles in ${category}` : 'Latest Stories'}
              </h2>
              <span className="text-xs text-neutral-500 font-medium">
                {meta.total} {meta.total === 1 ? 'Article' : 'Articles'}
              </span>
            </div>

            <BlogList
              posts={gridPosts}
              meta={meta}
              locale={locale}
              category={category}
            />
          </div>
        ) : (
          <EmptyState
            title="No articles found"
            description="We couldn't find any articles matching your criteria. Explore our tours or check back soon."
            cta={{
              label: 'Browse Tours',
              href: `/${locale}/tours`,
            }}
          />
        )}
      </div>
    </div>
  );
}
