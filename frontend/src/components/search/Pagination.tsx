'use client';

import { useRouter, useSearchParams, usePathname } from 'next/navigation';
import { useTranslations } from 'next-intl';

interface PaginationProps {
  currentPage: number;
  lastPage: number;
  ariaLabel?: string;
}

export default function Pagination({ currentPage, lastPage, ariaLabel }: PaginationProps) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const pathname = usePathname();
  const t = useTranslations('search');

  if (lastPage <= 1) return null;

  const goToPage = (page: number) => {
    const sp = new URLSearchParams(searchParams.toString());
    sp.set('page', String(page));
    // Stay on the current listing page (search, category, or destination)
    // rather than always jumping to /search (F3).
    router.push(`${pathname}?${sp.toString()}`);
  };

  const isFirst = currentPage <= 1;
  const isLast = currentPage >= lastPage;

  return (
    <nav className="flex items-center justify-center gap-3 py-8" aria-label={ariaLabel ?? t('paginationLabel')}>
      <button
        onClick={() => goToPage(currentPage - 1)}
        disabled={isFirst}
        className="rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-bookly-navy hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-50"
        aria-label={t('previousPage')}
      >
        {t('previous')}
      </button>

      <span className="text-sm text-text-muted">
        {t('pageOf', { currentPage, lastPage })}
      </span>

      <button
        onClick={() => goToPage(currentPage + 1)}
        disabled={isLast}
        className="rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-bookly-navy hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-50"
        aria-label={t('nextPage')}
      >
        {t('next')}
      </button>
    </nav>
  );
}
