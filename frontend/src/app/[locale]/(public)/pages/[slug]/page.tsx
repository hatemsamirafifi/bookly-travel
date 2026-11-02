import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { fetchPublicStaticPage } from '@/lib/api/pages';
import { sanitizeHtml } from '@/lib/sanitizeHtml';

interface StaticPageDetailPageProps {
  params: Promise<{ locale: string; slug: string }>;
}

export async function generateMetadata({
  params,
}: StaticPageDetailPageProps): Promise<Metadata> {
  const { locale, slug } = await params;

  const page = await fetchPublicStaticPage(slug, locale);
  if (!page) {
    return {
      title: 'Page Not Found | Bookly',
      robots: { index: false },
    };
  }

  return {
    title: page.meta_title ?? page.title,
    description: page.meta_description,
    ...(page.canonical_url ? { alternates: { canonical: page.canonical_url } } : {}),
    ...(page.og_image ? { openGraph: { images: [{ url: page.og_image }] } } : {}),
  };
}

export default async function StaticPageDetailPage({
  params,
}: StaticPageDetailPageProps) {
  const { locale, slug } = await params;

  const page = await fetchPublicStaticPage(slug, locale);
  if (!page) {
    notFound();
  }

  return (
    <main className="min-h-screen bg-surface-alt py-10 sm:py-16">
      <div className="mx-auto max-w-3xl px-4 sm:px-6">
        <h1 className="mb-8 text-3xl font-bold text-bookly-navy sm:text-4xl">
          {page.title}
        </h1>

        {page.translation_warning && (
          <div className="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
            {page.translation_warning}
          </div>
        )}

        <div
          className="prose prose-slate max-w-none rounded-xl border border-border bg-surface p-5 text-bookly-navy/80 shadow-sm sm:p-8"
          dangerouslySetInnerHTML={{ __html: sanitizeHtml(page.body) }}
        />
      </div>
    </main>
  );
}
