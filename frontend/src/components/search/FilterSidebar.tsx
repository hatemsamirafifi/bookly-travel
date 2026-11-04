'use client';

import { useState, useSyncExternalStore } from 'react';
import { useFilters } from '@/lib/hooks/useFilters';
import type { SearchResponse } from '@/lib/api/types';
import { useTranslations } from 'next-intl';

interface FilterSidebarProps {
  filterData: SearchResponse['filters'] | null;
}

const subscribeToDate = () => () => undefined;
const getClientDate = () => new Date().toISOString().split('T')[0];
const getServerDate = () => '';

interface FilterSectionProps {
  title: string;
  defaultOpen?: boolean;
  children: React.ReactNode;
}

function FilterSection({ title, defaultOpen = true, children }: FilterSectionProps) {
  const [isOpen, setIsOpen] = useState(defaultOpen);

  return (
    <fieldset className="border-b border-border py-4 last:border-b-0">
      <legend className="w-full">
        <button
          type="button"
          onClick={() => setIsOpen(!isOpen)}
          className="flex w-full items-center justify-between text-sm font-semibold text-bookly-navy hover:text-bookly-navy/80 focus-visible:outline-2 focus-visible:outline-focus"
          aria-expanded={isOpen}
        >
          <span>{title}</span>
          <svg
            className={`h-4 w-4 text-text-muted transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
          </svg>
        </button>
      </legend>
      {isOpen && <div className="pt-3">{children}</div>}
    </fieldset>
  );
}

export default function FilterSidebar({ filterData }: FilterSidebarProps) {
  const { filters, setFilter, activeFilterCount, clearAll } = useFilters();
  const t = useTranslations('search');
  const minDate = useSyncExternalStore(subscribeToDate, getClientDate, getServerDate);

  return (
    <aside
      className="hidden w-72 shrink-0 rounded-xl border border-border bg-surface p-5 shadow-xs lg:block"
      aria-label={t('filterPanelLabel') || 'Filter search results'}
    >
      <div className="mb-4 flex items-center justify-between border-b border-border pb-3">
        <h2 className="text-base font-bold text-bookly-navy">{t('filters') || 'Filters'}</h2>
        {activeFilterCount > 0 && (
          <button
            type="button"
            onClick={clearAll}
            className="text-xs font-semibold text-bookly-navy underline hover:text-bookly-navy/80 focus-visible:outline-2 focus-visible:outline-focus"
          >
            {t('clearAll') || 'Clear all'} ({activeFilterCount})
          </button>
        )}
      </div>

      {/* Categories */}
      {filterData?.categories && filterData.categories.length > 0 && (
        <FilterSection title={t('category') || 'Category'}>
          <div className="max-h-52 space-y-2.5 overflow-y-auto pr-1">
            {filterData.categories.map((cat) => (
              <label
                key={cat.slug}
                className="flex cursor-pointer items-center gap-2.5 text-sm text-text hover:text-bookly-navy"
              >
                <input
                  type="radio"
                  name="sidebar-category"
                  checked={filters.category === cat.slug}
                  onChange={() => setFilter('category', filters.category === cat.slug ? null : cat.slug)}
                  className="h-4 w-4 rounded-full border-border text-bookly-navy focus:ring-focus"
                />
                <span className="flex-1 truncate">{cat.name}</span>
                <span className="text-xs text-text-muted">({cat.count})</span>
              </label>
            ))}
          </div>
        </FilterSection>
      )}

      {/* Locations */}
      {filterData?.locations && filterData.locations.length > 0 && (
        <FilterSection title={t('location') || 'Location'}>
          <div className="max-h-52 space-y-2.5 overflow-y-auto pr-1">
            {filterData.locations.map((loc) => {
              const val = loc.slug ?? loc.name;
              return (
                <label
                  key={val}
                  className="flex cursor-pointer items-center gap-2.5 text-sm text-text hover:text-bookly-navy"
                >
                  <input
                    type="radio"
                    name="sidebar-location"
                    checked={filters.location === val}
                    onChange={() => setFilter('location', filters.location === val ? null : val)}
                    className="h-4 w-4 rounded-full border-border text-bookly-navy focus:ring-focus"
                  />
                  <span className="flex-1 truncate">{loc.name}</span>
                  <span className="text-xs text-text-muted">({loc.count})</span>
                </label>
              );
            })}
          </div>
        </FilterSection>
      )}

      {/* Price Range */}
      <FilterSection title={t('priceRange') || 'Price Range'}>
        <div className="flex items-center gap-2">
          <input
            type="number"
            min={0}
            placeholder={t('minPrice') || 'Min'}
            value={filters.price_min || ''}
            onChange={(e) => setFilter('price_min', e.target.value || null)}
            className="w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-bookly-navy placeholder:text-text-muted focus-visible:outline-2 focus-visible:outline-focus"
            aria-label={t('minPrice') || 'Min Price'}
          />
          <span className="text-text-muted">-</span>
          <input
            type="number"
            min={0}
            placeholder={t('maxPrice') || 'Max'}
            value={filters.price_max || ''}
            onChange={(e) => setFilter('price_max', e.target.value || null)}
            className="w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-bookly-navy placeholder:text-text-muted focus-visible:outline-2 focus-visible:outline-focus"
            aria-label={t('maxPrice') || 'Max Price'}
          />
        </div>
      </FilterSection>

      {/* Duration */}
      {filterData?.durations && filterData.durations.length > 0 && (
        <FilterSection title={t('duration') || 'Duration'}>
          <div className="space-y-2.5">
            {filterData.durations.map((d) => (
              <label
                key={d.value}
                className="flex cursor-pointer items-center gap-2.5 text-sm text-text hover:text-bookly-navy"
              >
                <input
                  type="radio"
                  name="sidebar-duration"
                  checked={filters.duration === d.value}
                  onChange={() => setFilter('duration', filters.duration === d.value ? null : d.value)}
                  className="h-4 w-4 rounded-full border-border text-bookly-navy focus:ring-focus"
                />
                <span className="flex-1 truncate">{d.label}</span>
                <span className="text-xs text-text-muted">({d.count})</span>
              </label>
            ))}
          </div>
        </FilterSection>
      )}

      {/* Date */}
      <FilterSection title={t('availableDate') || 'Available Date'} defaultOpen={false}>
        <input
          type="date"
          value={filters.date || ''}
          onChange={(e) => setFilter('date', e.target.value || null)}
          min={minDate || undefined}
          className="w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-bookly-navy focus-visible:outline-2 focus-visible:outline-focus"
          aria-label={t('filterByDate') || 'Filter by date'}
        />
      </FilterSection>
    </aside>
  );
}

