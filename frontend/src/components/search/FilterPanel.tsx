'use client';

import { useEffect, useState } from 'react';
import { useFilters } from '@/lib/hooks/useFilters';
import type { SearchResponse } from '@/lib/api/types';

interface FilterPanelProps {
  filterData: SearchResponse['filters'] | null;
}

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

  // Compute the date `min` bound on the client only, so the server-rendered
  // markup never embeds a `new Date()` value that would mismatch the client
  // render (hydration warning) and go stale across days.
  const [minDate, setMinDate] = useState<string>('');
  useEffect(() => {
    setMinDate(new Date().toISOString().split('T')[0]);
  }, []);

  return (
    <aside className="w-full rounded-xl border border-gray-200 bg-white p-4 lg:w-64" aria-label="Search filters">
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-base font-semibold text-[#0A2540]">Filters</h2>
        {activeFilterCount > 0 && (
          <button
            onClick={clearAll}
            className="text-xs font-medium text-[#0A2540] hover:text-[#071b2e] underline"
            aria-label={`Clear all ${activeFilterCount} active filters`}
          >
            Clear all ({activeFilterCount})
          </button>
        )}
      </div>

      {/* Category */}
      {filterData?.categories && filterData.categories.length > 0 && (
        <CollapsibleSection title="Category">
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
                <span className="text-xs text-gray-400">({cat.count})</span>
              </label>
            ))}
          </div>
        </CollapsibleSection>
      )}

      {/* Location */}
      {filterData?.locations && filterData.locations.length > 0 && (
        <CollapsibleSection title="Location">
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
                <span className="text-xs text-gray-400">({loc.count})</span>
              </label>
            ))}
          </div>
        </CollapsibleSection>
      )}

      {/* Price Range */}
      <CollapsibleSection title="Price Range">
        <div className="flex items-center gap-2">
          <input
            type="number"
            min={0}
            placeholder="Min"
            value={filters.price_min || ''}
            onChange={(e) => setFilter('price_min', e.target.value || null)}
            className="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-[#0A2540] focus:outline-none focus:ring-1 focus:ring-[#0A2540]/20"
            aria-label="Minimum price"
          />
          <span className="text-gray-400">-</span>
          <input
            type="number"
            min={0}
            placeholder="Max"
            value={filters.price_max || ''}
            onChange={(e) => setFilter('price_max', e.target.value || null)}
            className="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-[#0A2540] focus:outline-none focus:ring-1 focus:ring-[#0A2540]/20"
            aria-label="Maximum price"
          />
        </div>
      </CollapsibleSection>

      {/* Duration */}
      {filterData?.durations && filterData.durations.length > 0 && (
        <CollapsibleSection title="Duration">
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
                <span className="text-xs text-gray-400">({d.count})</span>
              </label>
            ))}
          </div>
        </CollapsibleSection>
      )}

      {/* Date */}
      <CollapsibleSection title="Available Date" defaultOpen={false}>
        <input
          type="date"
          value={filters.date || ''}
          onChange={(e) => setFilter('date', e.target.value || null)}
          min={minDate || undefined}
          className="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-[#0A2540] focus:outline-none focus:ring-1 focus:ring-[#0A2540]/20"
          aria-label="Filter by available date"
        />
      </CollapsibleSection>
    </aside>
  );
}
