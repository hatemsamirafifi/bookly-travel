'use client';

import { useEffect, useRef, useState, useSyncExternalStore } from 'react';
import { useFilters } from '@/lib/hooks/useFilters';
import type { SearchResponse } from '@/lib/api/types';
import { useTranslations } from 'next-intl';
import { useFocusTrap } from '@/lib/hooks/useFocusTrap';

interface FilterPanelProps {
  filterData: SearchResponse['filters'] | null;
}

const subscribeToDate = () => () => undefined;
const getClientDate = () => new Date().toISOString().split('T')[0];
const getServerDate = () => '';

interface CollapsibleSectionProps {
  title: string;
  defaultOpen?: boolean;
  children: React.ReactNode;
}

function CollapsibleSection({ title, defaultOpen = true, children }: CollapsibleSectionProps) {
  const [isOpen, setIsOpen] = useState(defaultOpen);

  return (
    <fieldset className="border-b border-gray-200 pb-4">
      <legend className="w-full">
        <button
          type="button"
          onClick={() => setIsOpen(!isOpen)}
          className="flex w-full items-center justify-between py-3 text-sm font-semibold text-[#0A2540] hover:text-[#071b2e]"
          aria-expanded={isOpen}
        >
          {title}
          <svg
            className={`h-4 w-4 text-[#5A6B7B] transition-transform ${isOpen ? 'rotate-180' : ''}`}
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
          </svg>
        </button>
      </legend>
      {isOpen && <div className="pt-1">{children}</div>}
    </fieldset>
  );
}

export default function FilterPanel({ filterData }: FilterPanelProps) {
  const { filters, setFilter, activeFilterCount, clearAll } = useFilters();
  const t = useTranslations('search');
  const [mobileOpen, setMobileOpen] = useState(false);
  const panelRef = useRef<HTMLElement>(null);
  const openButtonRef = useRef<HTMLButtonElement>(null);
  useFocusTrap(panelRef, mobileOpen);

  useEffect(() => {
    if (!mobileOpen) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        setMobileOpen(false);
        requestAnimationFrame(() => openButtonRef.current?.focus());
      }
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [mobileOpen]);

  const closeMobile = () => {
    setMobileOpen(false);
    requestAnimationFrame(() => openButtonRef.current?.focus());
  };

  // Compute the date `min` bound on the client only, so the server-rendered
  // markup never embeds a `new Date()` value that would mismatch the client
  // render (hydration warning) and go stale across days.
  const minDate = useSyncExternalStore(subscribeToDate, getClientDate, getServerDate);

  return (
    <>
    <button ref={openButtonRef} type="button" onClick={() => setMobileOpen(true)} className="mb-4 rounded-xl border border-border bg-surface px-4 py-2 text-sm font-semibold text-bookly-navy lg:hidden" aria-expanded={mobileOpen} aria-controls="search-filters">
      {t('filters')}{activeFilterCount > 0 ? ` (${activeFilterCount})` : ''}
    </button>
    {mobileOpen && <div className="fixed inset-0 z-40 bg-black/40 lg:hidden" onClick={closeMobile} aria-hidden="true" />}
    <aside ref={panelRef} id="search-filters" role={mobileOpen ? 'dialog' : undefined} aria-modal={mobileOpen ? true : undefined} className={`${mobileOpen ? 'fixed inset-y-0 left-0 z-50 block w-[min(88vw,20rem)] overflow-y-auto rounded-none shadow-xl' : 'hidden'} rounded-xl border border-border bg-surface p-4 lg:static lg:block lg:w-64 lg:shadow-none`} aria-label={t('filterPanelLabel')}>
      <button type="button" onClick={closeMobile} className="mb-3 rounded-lg px-2 py-1 text-sm font-medium text-bookly-navy lg:hidden" aria-label={t('closeFilters')}>
        {t('closeFilters')}
      </button>
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-base font-semibold text-bookly-navy">{t('filters')}</h2>
        {activeFilterCount > 0 && (
          <button
            onClick={clearAll}
            className="text-xs font-medium text-[#0A2540] hover:text-[#071b2e] underline"
            aria-label={`${t('clearAll')} (${activeFilterCount})`}
          >
            {t('clearAll')} ({activeFilterCount})
          </button>
        )}
      </div>

      {/* Category */}
      {filterData?.categories && filterData.categories.length > 0 && (
        <CollapsibleSection title={t('category')}>
          <div className="space-y-2 max-h-48 overflow-y-auto">
            {filterData.categories.map((cat) => (
              <label key={cat.slug} className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-gray-900">
                <input
                  type="radio"
                  name="category"
                  checked={filters.category === cat.slug}
                  onChange={() => setFilter('category', filters.category === cat.slug ? null : cat.slug)}
                  className="h-4 w-4 border-gray-300 text-[#0A2540] focus:ring-[#0A2540]"
                />
                <span className="flex-1">{cat.name}</span>
                <span className="text-xs text-gray-600">({cat.count})</span>
              </label>
            ))}
          </div>
        </CollapsibleSection>
      )}

      {/* Location */}
      {filterData?.locations && filterData.locations.length > 0 && (
        <CollapsibleSection title={t('location')}>
          <div className="space-y-2 max-h-48 overflow-y-auto">
            {filterData.locations.map((loc) => (
              <label key={loc.slug ?? loc.name} className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-gray-900">
                <input
                  type="radio"
                  name="location"
                  checked={filters.location === (loc.slug ?? loc.name)}
                  onChange={() => setFilter('location', filters.location === (loc.slug ?? loc.name) ? null : (loc.slug ?? loc.name as string))}
                  className="h-4 w-4 border-gray-300 text-[#0A2540] focus:ring-[#0A2540]"
                />
                <span className="flex-1">{loc.name}</span>
                <span className="text-xs text-gray-600">({loc.count})</span>
              </label>
            ))}
          </div>
        </CollapsibleSection>
      )}

      {/* Price Range */}
      <CollapsibleSection title={t('priceRange')}>
        <div className="flex items-center gap-2">
          <input
            type="number"
            min={0}
            placeholder={t('minPrice')}
            value={filters.price_min || ''}
            onChange={(e) => setFilter('price_min', e.target.value || null)}
            className="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-[#0A2540] focus:outline-none focus:ring-1 focus:ring-[#0A2540]/20"
            aria-label={t('minPrice')}
          />
          <span className="text-gray-400">-</span>
          <input
            type="number"
            min={0}
            placeholder={t('maxPrice')}
            value={filters.price_max || ''}
            onChange={(e) => setFilter('price_max', e.target.value || null)}
            className="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-[#0A2540] focus:outline-none focus:ring-1 focus:ring-[#0A2540]/20"
            aria-label={t('maxPrice')}
          />
        </div>
      </CollapsibleSection>

      {/* Duration */}
      {filterData?.durations && filterData.durations.length > 0 && (
        <CollapsibleSection title={t('duration')}>
          <div className="space-y-2">
            {filterData.durations.map((d) => (
              <label key={d.value} className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer hover:text-gray-900">
                <input
                  type="radio"
                  name="duration"
                  checked={filters.duration === d.value}
                  onChange={() => setFilter('duration', filters.duration === d.value ? null : d.value)}
                  className="h-4 w-4 border-gray-300 text-[#0A2540] focus:ring-[#0A2540]"
                />
                <span className="flex-1">{d.label}</span>
                <span className="text-xs text-gray-600">({d.count})</span>
              </label>
            ))}
          </div>
        </CollapsibleSection>
      )}

      {/* Date */}
      <CollapsibleSection title={t('availableDate')} defaultOpen={false}>
        <input
          type="date"
          value={filters.date || ''}
          onChange={(e) => setFilter('date', e.target.value || null)}
          min={minDate || undefined}
          className="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-[#0A2540] focus:outline-none focus:ring-1 focus:ring-[#0A2540]/20"
          aria-label={t('filterByDate')}
        />
      </CollapsibleSection>
      <button type="button" onClick={closeMobile} className="mt-5 w-full rounded-xl bg-bookly-gold px-4 py-2 text-sm font-semibold text-bookly-navy lg:hidden">
        {t('showResults')}
      </button>
    </aside>
    </>
  );
}
