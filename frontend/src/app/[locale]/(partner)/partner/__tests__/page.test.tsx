import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import PartnerDashboardPage from '../page';
import type { AnalyticsSummary as AnalyticsSummaryData } from '@/types/partner';

const usePartnerAnalyticsMock = jest.fn();
const refetch = jest.fn();

jest.mock('@/hooks/usePartnerAnalytics', () => ({
  usePartnerAnalytics: () => usePartnerAnalyticsMock(),
}));

jest.mock('@/lib/api/partner', () => ({
  getProfile: jest.fn().mockResolvedValue({ data: null }),
}));

jest.mock('@/components/partner/analytics/BookingsChart', () => ({
  BookingsChart: ({ data }: { data: unknown[] }) => (
    <div data-testid="bookings-chart" data-count={data.length} />
  ),
}));

jest.mock('@/components/partner/layout/PartnerSkeleton', () => ({
  PartnerAnalyticsSkeleton: () => <div data-testid="analytics-skeleton" />,
}));

const summary: AnalyticsSummaryData = {
  total_bookings: 9,
  total_revenue: 1800,
  average_rating: 4.1,
  conversion_rate: 2.5,
};

describe('PartnerDashboardPage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    refetch.mockReset();
  });

  it('shows the loading skeleton while analytics load', () => {
    usePartnerAnalyticsMock.mockReturnValue({
      summary: null,
      chartData: [],
      loading: true,
      error: null,
      refetch,
    });

    render(<PartnerDashboardPage />);

    expect(screen.getByTestId('analytics-skeleton')).toBeInTheDocument();
  });

  it('renders the summary and chart on success', async () => {
    usePartnerAnalyticsMock.mockReturnValue({
      summary,
      chartData: [{ date: '2026-06-01', bookings: 1, revenue: 100 }],
      loading: false,
      error: null,
      refetch,
    });

    render(<PartnerDashboardPage />);

    expect(screen.getByText('9')).toBeInTheDocument();
    expect(screen.getByTestId('bookings-chart')).toHaveAttribute('data-count', '1');
  });

  it('shows an error state with a retry button on failure', async () => {
    usePartnerAnalyticsMock.mockReturnValue({
      summary: null,
      chartData: [],
      loading: false,
      error: 'boom',
      refetch,
    });

    render(<PartnerDashboardPage />);

    const alert = screen.getByRole('alert');
    expect(alert).toBeInTheDocument();
    expect(screen.getByText('loadError')).toBeInTheDocument();

    fireEvent.click(screen.getByText('retry'));
    await waitFor(() => expect(refetch).toHaveBeenCalledTimes(1));
  });
});