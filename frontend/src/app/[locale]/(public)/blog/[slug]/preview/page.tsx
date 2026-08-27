import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getBlogPostPreview } from '@/lib/api/blog';
import BlogDetail from '@/components/blog/BlogDetail';
import { BlogUnavailable } from '@/components/blog/BlogUnavailable';

interface PreviewPageProps {
  params: Promise<{
    locale: string;
    slug: string;
  }>;
  searchParams: Promise<{
    token?: string;
  }>;
}

export const dynamic = 'force-dynamic';

export async function generateMetadata(): Promise<Metadata> {
  return {
    robots: {
      index: false,
      follow: false,
    },
  };
}

export default async function BlogPreviewPage({
  params,
  searchParams,
}: PreviewPageProps) {
  const { locale, slug } = await params;
  const { token } = await searchParams;

  if (!token) {
    notFound();
  }

  let articleResponse;
  try {
    articleResponse = await getBlogPostPreview(slug, token, locale);
  } catch (error: any) {
    if (error?.status === 403 || error?.status === 404) {
      notFound();
    }
    return <BlogUnavailable status={error?.status || 500} />;
  }

  const post = articleResponse.data;

  return (
    <article className="min-h-screen bg-white">
      {/* Draft Preview Indicator Banner */}
      <div className="sticky top-0 z-50 bg-amber-500 text-black px-4 py-2 text-center text-sm font-semibold shadow-md flex items-center justify-center gap-2">
        <span className="inline-block w-2.5 h-2.5 rounded-full bg-black animate-pulse" />
        Draft Preview Mode &mdash; Status: {post.status ? post.status.toUpperCase() : 'DRAFT'} &mdash; Private &amp; Not Indexed
      </div>

      <BlogDetail post={post} locale={locale} isPreview={true} />
    </article>
  );
}