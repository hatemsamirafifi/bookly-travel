import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getBlogPostPreview } from '@/lib/api/blog';
import { ApiError } from '@/lib/api/client';
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
  } catch (error: unknown) {
    const status = error instanceof ApiError ? error.status : 500;
    if (status === 403 || status === 404) {
      notFound();
    }
    return <BlogUnavailable status={status} />;
  }

  const post = articleResponse.data;

  return (
    <main className="min-h-screen bg-surface-alt py-6 sm:py-10">
      <div className="mx-auto max-w-5xl rounded-2xl border border-border bg-surface shadow-sm">
      <BlogDetail post={post} locale={locale} isPreview={true} />
      </div>
    </main>
  );
}
