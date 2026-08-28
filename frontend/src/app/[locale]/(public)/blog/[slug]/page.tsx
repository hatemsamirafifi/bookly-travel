import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getBlogPost } from '@/lib/api/blog';
import { NotFoundError, GoneError, RateLimitError } from '@/lib/api/client';
import BlogDetail from '@/components/blog/BlogDetail';
import { BlogUnavailable } from '@/components/blog/BlogUnavailable';
import { BlogPostingSchema, BreadcrumbListSchema } from '@/components/seo/StructuredData';

interface BlogDetailPageProps {
  params: Promise<{ locale: string; slug: string }>;
}

export async function generateMetadata({ params }: BlogDetailPageProps): Promise<Metadata> {
  const { locale, slug } = await params;

  try {
    const { data } = await getBlogPost(slug, locale);
    return {
      title: data.seo.meta_title,
      description: data.seo.meta_description,
      alternates: {
        canonical: data.seo.canonical_url,
        languages: data.seo.hreflang,
      },
      openGraph: {
        title: data.seo.meta_title,
        description: data.seo.meta_description,
        type: 'article',
        publishedTime: data.published_at || undefined,
        modifiedTime: data.updated_at || undefined,
        authors: [data.author.display_name],
        images: data.cover_image_url ? [{ url: data.cover_image_url }] : [],
      },
      twitter: {
        card: 'summary_large_image',
        title: data.seo.meta_title,
        description: data.seo.meta_description,
        images: data.cover_image_url ? [data.cover_image_url] : [],
      },
    };
  } catch (e) {
    if (e instanceof GoneError) {
      return {
        title: 'Article No Longer Available | Bookly',
        robots: { index: false, follow: false },
      };
    }
    return {
      title: 'Article Not Found | Bookly',
      robots: { index: false },
    };
  }
}

export default async function BlogDetailPage({ params }: BlogDetailPageProps) {
  const { locale, slug } = await params;
  const baseUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://bookly.com';

  let data;
  try {
    const response = await getBlogPost(slug, locale);
    data = response.data;
  } catch (e: any) {
    if (e instanceof GoneError) {
      return <BlogUnavailable status={410} isRemoved={true} />;
    }
    if (e instanceof RateLimitError) {
      return <BlogUnavailable status={429} retryAfterSeconds={e?.retryAfter ?? 10} />;
    }
    if (e instanceof NotFoundError) {
      notFound();
    }
    throw e;
  }

  const breadcrumbs = [
    { name: 'Home', url: `${baseUrl}/${locale}` },
    { name: 'Blog', url: `${baseUrl}/${locale}/blog` },
  ];

  if (data.category) {
    breadcrumbs.push({
      name: data.category.name,
      url: `${baseUrl}/${locale}/blog/category/${data.category.slug}`,
    });
  }

  breadcrumbs.push({
    name: data.title,
    url: `${baseUrl}/${locale}/blog/${data.slug}`,
  });

  return (
    <>
      <BlogPostingSchema
        headline={data.title}
        description={data.excerpt || data.seo.meta_description}
        datePublished={data.published_at}
        dateModified={data.updated_at}
        authorName={data.author.display_name}
        authorAvatarUrl={data.author.avatar_url}
        image={data.cover_image_url}
        inLanguage={locale}
        url={data.seo.canonical_url}
      />
      <BreadcrumbListSchema items={breadcrumbs} />
      <BlogDetail post={data} locale={locale} />
    </>
  );
}
