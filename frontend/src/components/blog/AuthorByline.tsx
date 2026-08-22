'use client';

import Image from 'next/image';
import type { BlogAuthorSummary } from '@/lib/api/types';
import { useTranslations } from 'next-intl';

interface AuthorBylineProps {
  author: BlogAuthorSummary;
  publishedAt?: string | null;
  readingTime?: number;
  locale: string;
}

export default function AuthorByline({
  author,
  publishedAt,
  readingTime,
  locale,
}: AuthorBylineProps) {
  const t = useTranslations('blog');

  const formattedDate = publishedAt
    ? new Date(publishedAt).toLocaleDateString(locale, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      })
    : null;

  return (
    <div className="flex items-start gap-4 py-4">
      {author.avatar_url ? (
        <Image
          src={author.avatar_url}
          alt={author.display_name}
          width={48}
          height={48}
          className="h-12 w-12 rounded-full object-cover border border-gray-200"
        />
      ) : (
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[#0A2540] text-lg font-bold text-white">
          {author.display_name.charAt(0)}
        </div>
      )}

      <div className="flex-1">
        <div className="flex flex-wrap items-center gap-x-2 text-sm">
          <span className="font-semibold text-gray-900">{author.display_name}</span>
          {formattedDate && (
            <>
              <span className="text-gray-400" aria-hidden="true">
                •
              </span>
              <time dateTime={publishedAt ?? ''} className="text-gray-500">
                {formattedDate}
              </time>
            </>
          )}
          {readingTime && (
            <>
              <span className="text-gray-400" aria-hidden="true">
                •
              </span>
              <span className="text-gray-500">{t('readingTime', { minutes: readingTime })}</span>
            </>
          )}
        </div>

        {author.bio && <p className="mt-1 text-sm text-gray-600 leading-relaxed">{author.bio}</p>}
      </div>
    </div>
  );
}
