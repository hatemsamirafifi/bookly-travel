'use client';

import { useFilters } from '@/lib/hooks/useFilters';
import { useTranslations } from 'next-intl';

export default function SortDropdown() {
  const { filters, setFilter } = useFilters();
  const t = useTranslations('search');
  const current = filters.sort || '';
  const options = [
    { value: '', label: t('sortRelevance') },
    { value: 'price_asc', label: t('sortPriceLow') },
    { value: 'price_desc', label: t('sortPriceHigh') },
    { value: 'rating', label: t('sortRating') },
    { value: 'newest', label: t('sortNewest') },
  ];

  return (
    <div className="flex items-center gap-2">
      <label htmlFor="sort-select" className="text-sm text-text-muted whitespace-nowrap">
        {t('sortBy')}
      </label>
      <select
        id="sort-select"
        value={current}
        onChange={(e) => setFilter('sort', e.target.value || null)}
        className="rounded-xl border border-border bg-surface px-3 py-2 text-sm text-bookly-navy focus-visible:border-bookly-navy focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
      >
        {options.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
    </div>
  );
}
