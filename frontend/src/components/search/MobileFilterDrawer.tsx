'use client';

import { useState, useRef, useEffect, useSyncExternalStore } from 'react';
import { useFilters, type FilterState } from '@/lib/hooks/useFilters';
import type { SearchResponse } from '@/lib/api/types';
import { useTranslations } from 'next-intl';
import { useFocusTrap } from '@/lib/hooks/useFocusTrap';

interface MobileFilterDrawerProps {
  filterData: SearchResponse['filters'] | null;
  totalResults?: number;
}

const subscribeToDate = () => () => undefined;
const getClientDate = () => new Date().toISOString().split('T')[0];
const getServerDate = () => '';

export default function MobileFilterDrawer({ filterData, totalResults = 0 }: MobileFilterDrawerProps) {
  const { filters, setMultipleFilters, activeFilterCount } = useFilters();
  const t = useTranslations('search');
  const [isOpen, setIsOpen] = useState(false);
  const [staged, setStaged] = useState<FilterState>({});
  const drawerRef = useRef<HTMLElement>(null);
  const openButtonRef = useRef<HTMLButtonElement>(null);
  const minDate = useSyncExternalStore(subscribeToDate, getClientDate, getServerDate);

  useFocusTrap(drawerRef, isOpen);

  // Sync staged state from active URL filters upon opening
  const handleOpen = () => {
    setStaged({ ...filters });
    setIsOpen(true);
  };

  const handleClose = () => {
    setIsOpen(false);
    requestAnimationFrame(() => openButtonRef.current?.focus());
  };

  useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        handleClose();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen]);

  const handleStageChange = (key: keyof FilterState, value: string | null) => {
    setStaged((prev) => ({
      ...prev,
      [key]: value ?? undefined,
    }));
  };

  const handleReset = () => {
    setStaged({});
  };

  const handleApply = () => {
    // Generate full update diff including explicit clearing of removed keys
    const updates: Partial<Record<keyof FilterState, string | null>> = {
      category: staged.category ?? null,
      location: staged.location ?? null,
      price_min: staged.price_min ?? null,
      price_max: staged.price_max ?? null,
      duration: staged.duration ?? null,
      date: staged.date ?? null,
    };
    setMultipleFilters(updates);
    handleClose();
  };

  return (
    <>
      <button
        ref={openButtonRef}
        type="button"
        onClick={handleOpen}
        className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-surface px-4 py-2.5 text-sm font-semibold text-bookly-navy shadow-xs transition hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-focus lg:hidden"
        aria-expanded={isOpen}
        aria-controls="mobile-filter-drawer"
      >
        <svg className="h-5 w-5 text-bookly-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
        </svg>
        <span>
          {t('filters') || 'Filters'}
          {activeFilterCount > 0 ? ` (${activeFilterCount})` : ''}
        </span>
      </button>

      {isOpen && (
        <div
          className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs transition-opacity lg:hidden"
          onClick={handleClose}
          aria-hidden="true"
        />
      )}

      {isOpen && (
        <aside
          ref={drawerRef}
          id="mobile-filter-drawer"
          role="dialog"
          aria-modal="true"
          aria-label={t('filterPanelLabel') || 'Filter search results'}
          className="fixed inset-y-0 right-0 z-50 flex w-full max-w-sm flex-col bg-surface shadow-2xl transition-transform lg:hidden"
        >
          {/* Header */}
          <div className="flex min-h-14 items-center justify-between border-b border-border px-4 py-3">
            <h2 className="text-lg font-bold text-bookly-navy">{t('filters') || 'Filters'}</h2>
            <button
              type="button"
              onClick={handleClose}
              className="flex min-h-11 min-w-11 items-center justify-center rounded-lg text-text-muted hover:bg-surface-alt hover:text-bookly-navy focus-visible:outline-2 focus-visible:outline-focus"
              aria-label={t('closeFilters') || 'Close filters'}
            >
              <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          {/* Scrollable Filter Content */}
          <div className="flex-1 overflow-y-auto px-4 py-4 space-y-6">
            {/* Categories */}
            {filterData?.categories && filterData.categories.length > 0 && (
              <fieldset className="border-b border-border pb-4">
                <legend className="mb-2 text-sm font-semibold text-bookly-navy">
                  {t('category') || 'Category'}
                </legend>
                <div className="space-y-1">
                  {filterData.categories.map((cat) => (
                    <label
                      key={cat.slug}
                      className="flex min-h-11 cursor-pointer items-center justify-between rounded-lg px-2 text-sm text-text hover:bg-surface-alt"
                    >
                      <div className="flex items-center gap-3">
                        <input
                          type="radio"
                          name="drawer-category"
                          checked={staged.category === cat.slug}
                          onChange={() => handleStageChange('category', staged.category === cat.slug ? null : cat.slug)}
                          className="h-5 w-5 border-border text-bookly-navy focus:ring-focus"
                        />
                        <span>{cat.name}</span>
                      </div>
                      <span className="text-xs text-text-muted">({cat.count})</span>
                    </label>
                  ))}
                </div>
              </fieldset>
            )}

            {/* Price Range */}
            <fieldset className="border-b border-border pb-4">
              <legend className="mb-2 text-sm font-semibold text-bookly-navy">
                {t('priceRange') || 'Price Range'}
              </legend>
              <div className="flex items-center gap-2">
                <input
                  type="number"
                  min={0}
                  placeholder={t('minPrice') || 'Min'}
                  value={staged.price_min || ''}
                  onChange={(e) => handleStageChange('price_min', e.target.value || null)}
                  className="min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-bookly-navy focus-visible:outline-2 focus-visible:outline-focus"
                  aria-label={t('minPrice') || 'Min Price'}
                />
                <span className="text-text-muted">-</span>
                <input
                  type="number"
                  min={0}
                  placeholder={t('maxPrice') || 'Max'}
                  value={staged.price_max || ''}
                  onChange={(e) => handleStageChange('price_max', e.target.value || null)}
                  className="min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-bookly-navy focus-visible:outline-2 focus-visible:outline-focus"
                  aria-label={t('maxPrice') || 'Max Price'}
                />
              </div>
            </fieldset>

            {/* Duration */}
            {filterData?.durations && filterData.durations.length > 0 && (
              <fieldset className="border-b border-border pb-4">
                <legend className="mb-2 text-sm font-semibold text-bookly-navy">
                  {t('duration') || 'Duration'}
                </legend>
                <div className="space-y-1">
                  {filterData.durations.map((d) => (
                    <label
                      key={d.value}
                      className="flex min-h-11 cursor-pointer items-center justify-between rounded-lg px-2 text-sm text-text hover:bg-surface-alt"
                    >
                      <div className="flex items-center gap-3">
                        <input
                          type="radio"
                          name="drawer-duration"
                          checked={staged.duration === d.value}
                          onChange={() => handleStageChange('duration', staged.duration === d.value ? null : d.value)}
                          className="h-5 w-5 border-border text-bookly-navy focus:ring-focus"
                        />
                        <span>{d.label}</span>
                      </div>
                      <span className="text-xs text-text-muted">({d.count})</span>
                    </label>
                  ))}
                </div>
              </fieldset>
            )}

            {/* Date */}
            <fieldset className="pb-4">
              <legend className="mb-2 text-sm font-semibold text-bookly-navy">
                {t('availableDate') || 'Available Date'}
              </legend>
              <input
                type="date"
                value={staged.date || ''}
                onChange={(e) => handleStageChange('date', e.target.value || null)}
                min={minDate || undefined}
                className="min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-bookly-navy focus-visible:outline-2 focus-visible:outline-focus"
                aria-label={t('filterByDate') || 'Filter by date'}
              />
            </fieldset>
          </div>

          {/* Sticky Bottom Confirmation Bar */}
          <div className="border-t border-border bg-surface p-4 shadow-lg flex items-center gap-3">
            <button
              type="button"
              onClick={handleReset}
              className="flex min-h-11 flex-1 items-center justify-center rounded-xl border border-border bg-surface px-4 py-2.5 text-sm font-semibold text-bookly-navy transition hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-focus"
            >
              {t('reset') || 'Reset'}
            </button>
            <button
              type="button"
              onClick={handleApply}
              className="flex min-h-11 flex-2 items-center justify-center rounded-xl bg-bookly-gold px-4 py-2.5 text-sm font-bold text-bookly-navy shadow-sm transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-focus"
            >
              {t('showToursCount', { count: totalResults }) || `Show ${totalResults} Tours`}
            </button>
          </div>
        </aside>
      )}
    </>
  );
}