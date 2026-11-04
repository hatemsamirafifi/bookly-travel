'use client';

import { useFilters } from '@/lib/hooks/useFilters';
import { useTranslations } from 'next-intl';

interface ActiveFilterChipsProps {
  categoryLabels?: Record<string, string>;
  locationLabels?: Record<string, string>;
}

export default function ActiveFilterChips({ categoryLabels = {}, locationLabels = {} }: ActiveFilterChipsProps) {
  const { filters, setFilter, clearAll } = useFilters();
  const t = useTranslations('search');

  const chips: { key: string; label: string; removeKey: Parameters<typeof setFilter>[0] }[] = [];

  if (filters.category) {
    const label = categoryLabels[filters.category] || filters.category;
    chips.push({ key: `category-${filters.category}`, label, removeKey: 'category' });
  }

  if (filters.location) {
    const label = locationLabels[filters.location] || filters.location;
    chips.push({ key: `location-${filters.location}`, label, removeKey: 'location' });
  }

  if (filters.price_min && filters.price_max) {
    chips.push({
      key: 'price',
      label: `$${filters.price_min} - $${filters.price_max}`,
      removeKey: 'price_min',
    });
  } else if (filters.price_min) {
    chips.push({ key: 'price_min', label: `From $${filters.price_min}`, removeKey: 'price_min' });
  } else if (filters.price_max) {
    chips.push({ key: 'price_max', label: `Up to $${filters.price_max}`, removeKey: 'price_max' });
  }

  if (filters.duration) {
    chips.push({ key: `duration-${filters.duration}`, label: filters.duration, removeKey: 'duration' });
  }

  if (filters.date) {
    chips.push({ key: `date-${filters.date}`, label: filters.date, removeKey: 'date' });
  }

  if (chips.length === 0) {
    return null;
  }

  const handleRemove = (chip: (typeof chips)[0]) => {
    if (chip.key === 'price') {
      setFilter('price_min', null);
      setFilter('price_max', null);
    } else {
      setFilter(chip.removeKey, null);
    }
  };

  return (
    <div
      className="mb-4 flex flex-wrap items-center gap-2"
      role="region"
      aria-label={t('activeFilters') || 'Active filters'}
    >
      <span className="text-xs font-medium text-text-muted">{t('activeFilters') || 'Active filters'}:</span>
      {chips.map((chip) => (
        <span
          key={chip.key}
          className="inline-flex items-center gap-1.5 rounded-full border border-border bg-surface px-3 py-1 text-xs font-medium text-bookly-navy shadow-sm"
        >
          <span>{chip.label}</span>
          <button
            type="button"
            onClick={() => handleRemove(chip)}
            className="flex h-4 w-4 items-center justify-center rounded-full text-text-muted hover:bg-surface-alt hover:text-bookly-navy focus-visible:outline-2 focus-visible:outline-focus"
            aria-label={`Remove ${chip.label} filter`}
          >
            <span aria-hidden="true">&times;</span>
          </button>
        </span>
      ))}
      <button
        type="button"
        onClick={clearAll}
        className="ml-1 text-xs font-semibold text-bookly-navy underline hover:text-bookly-navy/80 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
      >
        {t('clearAll') || 'Clear all'}
      </button>
    </div>
  );
}

