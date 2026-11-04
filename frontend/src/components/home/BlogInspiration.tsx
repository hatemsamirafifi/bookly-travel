import Link from 'next/link';
import { useTranslations } from 'next-intl';
import type { BlogListResponse } from '@/lib/api/types';
import BlogCard from '@/components/blog/BlogCard';

interface BlogInspirationProps {
  articles: BlogListResponse['data'];
  locale: string;
}

export default function BlogInspiration({ articles, locale }: BlogInspirationProps) {
  const t = useTranslations('home');
  if (!articles || articles.length === 0) return null;

  return (
    <section className="bg-surface py-12" aria-labelledby="home-editorial-title">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mb-8 flex items-center justify-between gap-4">
          <div>
            <p className="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-text-muted">
              {t('editorialEyebrow')}
            </p>
            <h2 id="home-editorial-title" className="text-2xl font-bold text-bookly-navy">
              {t('editorialTitle')}
            </h2>
          </div>
          <Link
            href={`/${locale}/blog`}
            className="text-sm font-semibold text-bookly-navy underline-offset-4 hover:underline focus-visible:underline"
          >
            {t('viewAllArticles')}
          </Link>
        </div>
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {articles.map((post) => (
            <BlogCard key={post.id} post={post} locale={locale} />
          ))}
        </div>
      </div>
    </section>
  );
}
