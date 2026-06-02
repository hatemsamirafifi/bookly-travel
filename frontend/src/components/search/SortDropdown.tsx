'use client';

import { useFilters } from '@/lib/hooks/useFilters';

const SORT_OPTIONS = [
  { value: '', label: 'Relevance' },
  { value: 'price_asc', label: 'Price: Low to High' },
  { value: 'price_desc', label: 'Price: High to Low' },
  { value: 'rating', label: 'Top Rated' },
  { value: 'newest', label: 'Newest' },
];

export default function SortDropdown() {
  const { filters, setFilter } = useFilters();
  const current = filters.sort || '';

  return (
    <div className="flex items-center gap-2">
      <label htmlFor="sort-select" className="text-sm text-[#5A6B7B] whitespace-nowrap">
        Sort by:
      </label>
      <select
        id="sort-select"
        value={current}
        onChange={(e) => setFilter('sort', e.target.value || null)}
        className="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-[#0A2540] focus:border-[#0A2540] focus:outline-none focus:ring-2 focus:ring-[#0A2540]/20"
      >
        {SORT_OPTIONS.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
    </div>
  );
}
