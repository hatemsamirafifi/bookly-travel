'use client';

import Image from 'next/image';
import Link from 'next/link';
import type { BlogPostDetail } from '@/lib/api/types';
import { getImagePlaceholderProps } from '@/lib/images';
import AuthorByline from './AuthorByline';
import RelatedTours from './RelatedTours';
import RelatedPosts from './RelatedPosts';
import { useTranslations } from 'next-intl';

interface BlogDetailProps {
  post: BlogPostDetail;
  locale: string;
  isPreview?: boolean;
}

export default function BlogDetail({ post, locale, isPreview }: BlogDetailProps) {
  const t = useTranslations('blog');

  return (
    <article className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {isPreview && (
        <div className="mb-6 rounded-lg bg-amber-500/10 border border-amber-500/30 p-4 text-center text-sm font-medium text-amber-800">
          {t('previewBanner')}
        </div>
      )}

      {post.translation_warning && (
        <div className="mb-6 rounded-lg bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
          {t('partialTranslation')}
        </div>
      )}

      <header className="mb-8">
        <div className="mb-4 flex flex-wrap items-center gap-2">
          <Link
            href={`/${locale}/blog`}
            className="text-sm font-medium text-blue-600 hover:text-blue-800"
          >
            ← {t('backToBlog')}
          </Link>
          {post.category && (
            <>
              <span className="text-gray-400" aria-hidden="true">
                /
              </span>
              <Link
                href={`/${locale}/blog/category/${post.category.slug}`}
                className="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200"
              >
                {post.category.name}
              </Link>
            </>
          )}
        </div>

        <h1 className="text-3xl font-extrabold tracking-tight text-[#0A2540] sm:text-4xl lg:text-5xl">
          {post.title}
        </h1>

        <div className="mt-6 border-y border-gray-100">
          <AuthorByline
            author={post.author}
            publishedAt={post.published_at}
            readingTime={post.reading_time}
            locale={locale}
          />
        </div>
      </header>

      {post.cover_image_url && (
        <div className="relative mb-10 aspect-[16/9] w-full overflow-hidden rounded-2xl bg-gray-100 shadow-md">
          <Image
            src={post.cover_image_url}
            alt={post.title}
            fill
            priority
            sizes="(min-width: 1024px) 896px, 100vw"
            className="object-cover"
            {...getImagePlaceholderProps()}
          />
        </div>
      )}

      <div
        className="prose prose-lg max-w-none text-gray-800 prose-headings:text-[#0A2540] prose-a:text-blue-600 hover:prose-a:text-blue-800 prose-img:rounded-xl"
        dangerouslySetInnerHTML={{ __html: post.body }}
      />

      <RelatedTours tours={post.related_tours} locale={locale} />
      <RelatedPosts posts={post.related_posts} locale={locale} />
    </article>
  );
}
