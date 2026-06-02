'use client';

import { useCallback } from 'react';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select';
import { Search, X } from 'lucide-react';
import type { BookingStatus } from '@/types/partner';

export interface BookingFilterValues {
  status?: BookingStatus;
  date_from?: string;
  date_to?: string;
  tour_id?: string;
  search?: string;
}

interface BookingFiltersProps {
  /** Current filter values */
  filters: BookingFilterValues;
  /** Called when any filter changes */
  onFiltersChange: (filters: BookingFilterValues) => void;
  /** Optional list of tours for the tour filter dropdown */
  tours?: { id: string | number; title: string }[];
}

const STATUS_OPTIONS: { value: BookingStatus; label: string }[] = [
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'cancellation_requested', label: 'Cancellation Requested' },
];

export function BookingFilters({ filters, onFiltersChange, tours }: BookingFiltersProps) {
  const updateFilter = useCallback(
    <K extends keyof BookingFilterValues>(key: K, value: BookingFilterValues[K]) => {
      onFiltersChange({ ...filters, [key]: value });
    },
    [filters, onFiltersChange]
  );

  const clearFilters = useCallback(() => {
    onFiltersChange({});
  }, [onFiltersChange]);

  const hasActiveFilters = Object.values(filters).some(
    (v) => v !== undefined && v !== ''
  );

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-[#0A2540]">Filters</h3>
        {hasActiveFilters && (
          <button
            type="button"
            onClick={clearFilters}
            className="text-xs text-gray-500 hover:text-[#0A2540] flex items-center gap-1 transition-colors"
          >
            <X className="w-3 h-3" />
            Clear all
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        {/* Search by reference */}
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <Input
            placeholder="Search by reference..."
            value={filters.search ?? ''}
            onChange={(e) => updateFilter('search', e.target.value || undefined)}
            className="pl-9"
          />
        </div>

        {/* Status filter */}
        <div>
          <Select
            value={filters.status ?? ''}
            onValueChange={(v) => updateFilter('status', (v || undefined) as BookingStatus | undefined)}
          >
            <SelectTrigger className="w-full">
              <SelectValue placeholder="All statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">All statuses</SelectItem>
              {STATUS_OPTIONS.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Date from */}
        <div>
          <Input
            type="date"
            placeholder="Date from"
            value={filters.date_from ?? ''}
            onChange={(e) => updateFilter('date_from', e.target.value || undefined)}
          />
        </div>

        {/* Date to */}
        <div>
          <Input
            type="date"
            placeholder="Date to"
            value={filters.date_to ?? ''}
            onChange={(e) => updateFilter('date_to', e.target.value || undefined)}
          />
        </div>
      </div>

      {/* Tour filter (if tours are provided) */}
      {tours && tours.length > 0 && (
        <div>
          <Select
            value={filters.tour_id ? String(filters.tour_id) : ''}
            onValueChange={(v) => updateFilter('tour_id', v || undefined)}
          >
            <SelectTrigger className="w-full sm:w-64">
              <SelectValue placeholder="All tours" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">All tours</SelectItem>
              {tours.map((tour) => (
                <SelectItem key={String(tour.id)} value={String(tour.id)}>
                  {tour.title}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}

      {/* Active filter badges */}
      {hasActiveFilters && (
        <div className="flex flex-wrap gap-2">
          {filters.status && (
            <FilterBadge
              label={`Status: ${STATUS_OPTIONS.find((o) => o.value === filters.status)?.label ?? filters.status}`}
              onRemove={() => updateFilter('status', undefined)}
            />
          )}
          {filters.date_from && (
            <FilterBadge
              label={`From: ${filters.date_from}`}
              onRemove={() => updateFilter('date_from', undefined)}
            />
          )}
          {filters.date_to && (
            <FilterBadge
              label={`To: ${filters.date_to}`}
              onRemove={() => updateFilter('date_to', undefined)}
            />
          )}
          {filters.search && (
            <FilterBadge
              label={`Search: ${filters.search}`}
              onRemove={() => updateFilter('search', undefined)}
            />
          )}
          {filters.tour_id && (
            <FilterBadge
              label={`Tour: ${tours?.find((t) => String(t.id) === String(filters.tour_id))?.title ?? String(filters.tour_id)}`}
              onRemove={() => updateFilter('tour_id', undefined)}
            />
          )}
        </div>
      )}
    </div>
  );
}

function FilterBadge({ label, onRemove }: { label: string; onRemove: () => void }) {
  return (
    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-[#FFB800]/10 text-[#0A2540] border border-[#FFB800]/20">
      {label}
      <button
        type="button"
        onClick={onRemove}
        className="hover:text-red-500 transition-colors"
        aria-label={`Remove filter: ${label}`}
      >
        <X className="w-3 h-3" />
      </button>
    </span>
  );
}