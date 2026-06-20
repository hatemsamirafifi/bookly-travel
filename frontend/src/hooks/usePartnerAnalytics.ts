'use client';

import { useQuery } from '@tanstack/react-query';
import { getAnalytics } from '@/lib/api/partner';
import type {
  AnalyticsBookingsPoint,
  AnalyticsSummary,
  DateRangeFilter,
} from '@/types/partner';

/** Chart datum expected by {@link BookingsChart}. Alias of the API point type. */
export type BookingsChartData = AnalyticsBookingsPoint;

export interface UsePartnerAnalyticsResult {
  summary: AnalyticsSummary | null;
  chartData: BookingsChartData[];
  loading: boolean;
  error: string | null;
  refetch: () => void;
}

function queryKey(range?: DateRangeFilter, tourId?: string | number) {
  return ['partner', 'analytics', { from: range?.from ?? null, to: range?.to ?? null, tourId: tourId ?? null }] as const;
}

/**
 * Fetches partner analytics via TanStack Query and maps the API
 * `bookings_over_time` series onto the chart datum. The API point already uses
 * a `bookings` field, so the mapping is a defensive copy with zero-defaults
 * for any malformed values. Caching follows the app's existing query
 * conventions (30s staleTime, 1 retry).
 */
export function usePartnerAnalytics(range?: DateRangeFilter, tourId?: string | number): UsePartnerAnalyticsResult {
  const query = useQuery({
    queryKey: queryKey(range, tourId),
    queryFn: () => getAnalytics(range, tourId),
    staleTime: 30_000,
    retry: 1,
  });

  const summary = query.data?.summary ?? null;
  const chartData: BookingsChartData[] = (query.data?.bookings_over_time ?? []).map((point) => ({
    date: point.date,
    bookings: typeof point.bookings === 'number' ? point.bookings : 0,
    revenue: typeof point.revenue === 'number' ? point.revenue : 0,
  }));
  const error = query.error instanceof Error ? query.error.message : query.error ? 'Failed to load analytics' : null;

  return {
    summary,
    chartData,
    loading: query.isLoading,
    error,
    refetch: () => {
      void query.refetch();
    },
  };
}