'use client';

import Image from 'next/image';
import Link from 'next/link';
import type { BlogPostCard } from '@/lib/api/types';
import { getImagePlaceholderProps } from '@/lib/images';
import { useTranslations } from 'next-intl';

interface BlogCardProps {
  post: BlogPostCard;
  locale: string;
}

export default function BlogCard({ post, locale }: BlogCardProps) {
  const t = useTranslations('blog');

  const formattedDate = post.published_at
    ? new Date(post.published_at).toLocaleDateString(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      })
    : null;

  return (
    <article className="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md focus-within:ring-2 focus-within:ring-[#0A2540]">
      <Link
        href={`/${locale}/blog/${post.slug}`}
        className="relative aspect-[16/10] w-full overflow-hidden bg-gray-100 block"
      >
        {post.cover_image_url ? (
          <Image
            src={post.cover_image_url}
            alt={post.title}
            fill
            sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
            className="object-cover transition-transform duration-300 group-hover:scale-105"
            {...getImagePlaceholderProps()}
          />
        ) : (
          <div className="flex h-full items-center justify-center text-gray-400">
            <svg
              className="h-12 w-12"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.5}
                d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"
              />
            </svg>
          </div>
        )}
        {post.category && (
          <span className="absolute left-3 top-3 rounded-md bg-white/90 px-2.5 py-1 text-xs font-semibold text-[#0A2540] shadow-sm backdrop-blur-sm">
            {post.category.name}
          </span>
        )}
      </Link>

      <div className="flex flex-1 flex-col p-5">
        <div className="mb-2 flex items-center gap-2 text-xs text-gray-500">
          {formattedDate && <time dateTime={post.published_at ?? ''}>{formattedDate}</time>}
          {formattedDate && <span aria-hidden="true">·</span>}
          <span>{t('readingTime', { minutes: post.reading_time })}</span>
        </div>

        <h3 className="mb-2 text-lg font-bold text-[#0A2540] group-hover:text-blue-700 line-clamp-2">
          <Link href={`/${locale}/blog/${post.slug}`}>{post.title}</Link>
        </h3>

        <p className="mb-4 text-sm text-gray-600 line-clamp-3 flex-1">{post.excerpt}</p>

        <div className="flex items-center justify-between border-t border-gray-100 pt-3 text-xs text-gray-500">
          <div className="flex items-center gap-2">
            {post.author.avatar_url ? (
              <Image
                src={post.author.avatar_url}
                alt={post.author.display_name}
                width={24}
                height={24}
                className="h-6 w-6 rounded-full object-cover"
              />
            ) : (
              <div className="flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-xs font-medium text-gray-700">
                {post.author.display_name.charAt(0)}
              </div>
            )}
            <span className="font-medium text-gray-700">{post.author.display_name}</span>
          </div>

          {post.translation_warning && (
            <span
              className="inline-flex items-center rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-700"
              title={t('partialTranslation')}
            >
              EN
            </span>
          )}
        </div>
      </div>
    </article>
  );
}
