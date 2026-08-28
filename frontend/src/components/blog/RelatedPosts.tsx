'use client';

import type { BlogPostCard } from '@/lib/api/types';
import BlogCard from './BlogCard';
import { useTranslations } from 'next-intl';

interface RelatedPostsProps {
  posts: BlogPostCard[];
  locale: string;
}

export default function RelatedPosts({ posts, locale }: RelatedPostsProps) {
  const t = useTranslations('blog');

  if (!posts || posts.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="related-posts-heading" className="mt-12 border-t border-gray-200 pt-10">
      <h2 id="related-posts-heading" className="mb-6 text-2xl font-bold text-[#0A2540]">
        {t('relatedPosts')}
      </h2>

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {posts.map((post) => (
          <BlogCard key={post.id} post={post} locale={locale} />
        ))}
      </div>
    </section>
  );
}
