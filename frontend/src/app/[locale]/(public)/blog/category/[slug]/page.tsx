import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import Link from 'next/link';
import { getBlogCategory } from '@/lib/api/blog';
import BlogList from '@/components/blog/BlogList';
import EmptyState from '@/components/ui/EmptyState';
import { BreadcrumbListSchema } from '@/components/seo/StructuredData';

interface CategoryPageProps {
  params: Promise<{
    locale: string;
    slug: string;
  }>;
  searchParams: Promise<{
    page?: string;
  }>;
}

export async function generateMetadata({
  params,
  searchParams,
}: CategoryPageProps): Promise<Metadata> {
  const { locale, slug } = await params;
  const { page } = await searchParams;

  const validLocales = ['en', 'es', 'it'];
  const activeLocale = validLocales.includes(locale) ? locale : 'en';

  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || 'https://booklytravel.com';
  const queryStr = page && page !== '1' ? `?page=${encodeURIComponent(page)}` : '';

  try {
    const categoryData = await getBlogCategory(slug, activeLocale, { page: 1, per_page: 1 });
    const category = categoryData.category;
    const title = `${category.name} Articles | Bookly Travel Blog`;
    const description =
      category.description ||
      `Explore our curated travel articles, guides, and tips in ${category.name}.`;

    const canonicalUrl = `${baseUrl}/${activeLocale}/blog/category/${slug}${queryStr}`;

    return {
      title,
      description,
      alternates: {
        canonical: canonicalUrl,
        languages: {
          en: `${baseUrl}/en/blog/category/${slug}${queryStr}`,
          es: `${baseUrl}/es/blog/category/${slug}${queryStr}`,
          it: `${baseUrl}/it/blog/category/${slug}${queryStr}`,
        },
      },
      openGraph: {
        title,
        description,
        url: canonicalUrl,
        type: 'website',
      },
    };
  } catch {
    return {
      title: 'Blog Category | Bookly Travel',
    };
  }
}

export default async function BlogCategoryPage({
  params,
  searchParams,
}: CategoryPageProps) {
  const { locale, slug } = await params;
  const { page } = await searchParams;

  const validLocales = ['en', 'es', 'it'];
  if (!validLocales.includes(locale)) {
    notFound();
  }

  const currentPage = page ? parseInt(page, 10) : 1;
  const pageNum = isNaN(currentPage) || currentPage < 1 ? 1 : currentPage;

  let categoryResponse;
  try {
    categoryResponse = await getBlogCategory(slug, locale, {
      page: pageNum,
      per_page: 12,
    });
  } catch {
    notFound();
  }

  const { category, posts, meta } = categoryResponse;

  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || 'https://booklytravel.com';
  const breadcrumbItems = [
    { name: 'Home', item: `${baseUrl}/${locale}` },
    { name: 'Blog', item: `${baseUrl}/${locale}/blog` },
    { name: category.name, item: `${baseUrl}/${locale}/blog/category/${category.slug}` },
  ];

  return (
    <main className="min-h-screen bg-neutral-50 py-12">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Breadcrumb Schema */}
        <BreadcrumbListSchema itemListElement={breadcrumbItems} />

        {/* Breadcrumbs Nav */}
        <nav aria-label="Breadcrumbs" className="mb-6">
          <ol className="flex items-center space-x-2 text-sm text-neutral-500">
            <li>
              <Link href={`/${locale}`} className="hover:text-primary-600">
                Home
              </Link>
            </li>
            <li>
              <span className="text-neutral-400">/</span>
            </li>
            <li>
              <Link href={`/${locale}/blog`} className="hover:text-primary-600">
                Blog
              </Link>
            </li>
            <li>
              <span className="text-neutral-400">/</span>
            </li>
            <li className="font-medium text-neutral-900" aria-current="page">
              {category.name}
            </li>
          </ol>
        </nav>

        {/* Category Header */}
        <header className="mb-10 pb-6 border-b border-neutral-200">
          <div className="flex items-center gap-3 mb-2">
            <span className="inline-flex items-center rounded-full bg-primary-100 px-3 py-1 text-xs font-semibold text-primary-800">
              Category
            </span>
            <span className="text-xs text-neutral-500">
              {meta.total} {meta.total === 1 ? 'Article' : 'Articles'}
            </span>
          </div>
          <h1 className="text-3xl sm:text-4xl font-extrabold text-neutral-900 tracking-tight">
            {category.name}
          </h1>
          {category.description && (
            <p className="mt-3 max-w-3xl text-base text-neutral-600">
              {category.description}
            </p>
          )}
        </header>

        {/* Posts Grid & Pagination or Empty State */}
        {posts.length > 0 ? (
          <BlogList
            posts={posts}
            meta={meta}
            locale={locale}
          />
        ) : (
          <EmptyState
            title={`No articles in ${category.name} yet`}
            description="We are constantly adding new guides and stories. Check back soon or explore other categories."
            cta={{
              label: 'View All Blog Posts',
              href: `/${locale}/blog`,
            }}
          />
        )}
      </div>
    </main>
  );
}
