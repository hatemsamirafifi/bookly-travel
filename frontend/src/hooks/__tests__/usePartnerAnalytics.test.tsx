import { render, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import React, { useEffect } from 'react';
import { usePartnerAnalytics, type UsePartnerAnalyticsResult } from '@/hooks/usePartnerAnalytics';
import type { AnalyticsResponse } from '@/types/partner';

const getAnalyticsMock = jest.fn();
jest.mock('@/lib/api/partner', () => ({
  getAnalytics: (...args: unknown[]) => getAnalyticsMock(...args),
}));

function createClient() {
  return new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: 0 } } });
}

function renderWithClient(onResult: (r: UsePartnerAnalyticsResult) => void) {
  const Probe = () => {
    const result = usePartnerAnalytics();
    useEffect(() => {
      onResult(result);
    }, [result]);
    return null;
  };
  return render(
    <QueryClientProvider client={createClient()}>
      <Probe />
    </QueryClientProvider>
  );
}

const response: AnalyticsResponse = {
  summary: {
    total_bookings: 10,
    total_revenue: 2000,
    average_rating: 4.2,
    conversion_rate: 5.0,
  },
  bookings_over_time: [
    { date: '2026-06-01', bookings: 2, revenue: 400 },
    { date: '2026-06-02', bookings: 3, revenue: 600 },
  ],
  period: { from: '2026-06-01', to: '2026-06-30' },
};

describe('usePartnerAnalytics', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('maps bookings_over_time onto chart data with the bookings field', async () => {
    getAnalyticsMock.mockResolvedValue(response);
    let latest: UsePartnerAnalyticsResult | null = null;

    renderWithClient((r) => {
      latest = r;
    });

    await waitFor(() => expect(latest?.loading).toBe(false));
    expect(latest?.error).toBeNull();
    expect(latest?.summary).toEqual(response.summary);
    expect(latest?.chartData).toEqual([
      { date: '2026-06-01', bookings: 2, revenue: 400 },
      { date: '2026-06-02', bookings: 3, revenue: 600 },
    ]);
  });

  it('handles an empty bookings_over_time array', async () => {
    getAnalyticsMock.mockResolvedValue({ ...response, bookings_over_time: [] });
    let latest: UsePartnerAnalyticsResult | null = null;

    renderWithClient((r) => {
      latest = r;
    });

    await waitFor(() => expect(latest?.loading).toBe(false));
    expect(latest?.chartData).toEqual([]);
    expect(latest?.summary).not.toBeNull();
  });

  it('defaults malformed point values to zero', async () => {
    getAnalyticsMock.mockResolvedValue({
      ...response,
      bookings_over_time: [
        { date: '2026-06-01', bookings: 5, revenue: 100 },
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        { date: '2026-06-02', bookings: undefined, revenue: undefined } as any,
      ],
    });
    let latest: UsePartnerAnalyticsResult | null = null;

    renderWithClient((r) => {
      latest = r;
    });

    await waitFor(() => expect(latest?.loading).toBe(false));
    expect(latest?.chartData).toEqual([
      { date: '2026-06-01', bookings: 5, revenue: 100 },
      { date: '2026-06-02', bookings: 0, revenue: 0 },
    ]);
  });

  it('exposes an error message when the API call fails', async () => {
    getAnalyticsMock.mockRejectedValue(new Error('Network error'));
    let latest: UsePartnerAnalyticsResult | null = null;

    renderWithClient((r) => {
      latest = r;
    });

    await waitFor(
      () => expect(latest?.error).toBe('Network error'),
      { timeout: 8000 }
    );
    expect(latest?.summary).toBeNull();
    expect(latest?.chartData).toEqual([]);
  });
});