'use client';

import { useState, useCallback, useTransition, useEffect } from 'react';
import { useRouter, useParams, useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';

interface SearchBarProps {
  initialQuery?: string;
  compact?: boolean;
}

export default function SearchBar({ initialQuery = '', compact = false }: SearchBarProps) {
  const [query, setQuery] = useState(initialQuery);
  const [isPending, startTransition] = useTransition();
  const router = useRouter();
  const params = useParams();
  const searchParams = useSearchParams();
  const locale = (params?.locale as string) || 'en';
  const t = useTranslations('search');

  useEffect(() => {
    setQuery(initialQuery);
  }, [initialQuery]);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      const trimmed = query.trim();
      startTransition(() => {
        const sp = new URLSearchParams(searchParams ? searchParams.toString() : '');
        if (trimmed) {
          sp.set('q', trimmed);
        } else {
          sp.delete('q');
        }
        sp.delete('page');
        const qs = sp.toString();
        router.push(`/${locale}/search${qs ? `?${qs}` : ''}`);
      });
    },
    [query, locale, router, searchParams]
  );

  return (
    <form
      onSubmit={handleSubmit}
      role="search"
      aria-busy={isPending}
      className={compact ? 'w-full max-w-sm' : 'w-full max-w-2xl'}
    >
      <label htmlFor="search-input" className="sr-only">
        {t('searchTours')}
      </label>
      <div className="relative">
        <input
          id="search-input"
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={t('placeholder')}
          className="w-full rounded-xl border border-border bg-surface px-4 py-3 pl-11 text-bookly-navy shadow-xs focus-visible:border-bookly-navy focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
          autoComplete="off"
        />
        <svg
          className="absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-text-muted"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
          />
        </svg>
        <button
          type="submit"
          className="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg bg-bookly-gold px-4 py-1.5 text-sm font-semibold text-bookly-navy shadow-xs transition hover:brightness-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2"
        >
          {t('searchButton')}
        </button>
      </div>
      {isPending && (
        <p role="status" className="mt-2 text-sm text-text-muted">
          {t('loading')}
        </p>
      )}
    </form>
  );
}
