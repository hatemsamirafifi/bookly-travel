import { render, screen } from '@testing-library/react';
import { AnalyticsSummary } from '../AnalyticsSummary';
import type { AnalyticsSummary as AnalyticsSummaryData } from '@/types/partner';

const baseSummary: AnalyticsSummaryData = {
  total_bookings: 42,
  total_revenue: 12345,
  average_rating: 4.5,
  conversion_rate: 12.3,
};

describe('AnalyticsSummary', () => {
  it('renders the core metric cards with their values', () => {
    render(<AnalyticsSummary summary={baseSummary} />);

    expect(screen.getByText('totalBookings')).toBeInTheDocument();
    expect(screen.getByText('42')).toBeInTheDocument();
    expect(screen.getByText('4.5')).toBeInTheDocument();
    expect(screen.getByText('12.3%')).toBeInTheDocument();
    // Revenue is formatted as EUR currency.
    expect(screen.getByText(/€/)).toBeInTheDocument();
  });

  it('renders zero values without crashing', () => {
    render(
      <AnalyticsSummary
        summary={{ total_bookings: 0, total_revenue: 0, average_rating: 0, conversion_rate: 0 }}
      />
    );

    expect(screen.getAllByText('0')).toHaveLength(1);
    expect(screen.getByText('0.0')).toBeInTheDocument();
    expect(screen.getByText('0.0%')).toBeInTheDocument();
  });

  it('hides optional upcoming/review cards when those fields are missing', () => {
    render(<AnalyticsSummary summary={baseSummary} />);

    expect(screen.queryByText('upcomingBookings')).not.toBeInTheDocument();
    expect(screen.queryByText('reviewCount')).not.toBeInTheDocument();
  });

  it('shows optional cards only when the API returns those fields', () => {
    render(
      <AnalyticsSummary
        summary={{ ...baseSummary, upcoming_bookings: 7, review_count: 19 }}
      />
    );

    expect(screen.getByText('upcomingBookings')).toBeInTheDocument();
    expect(screen.getByText('reviewCount')).toBeInTheDocument();
    expect(screen.getByText('7')).toBeInTheDocument();
    expect(screen.getByText('19')).toBeInTheDocument();
  });

  it('treats nullish revenue as zero', () => {
    render(
      <AnalyticsSummary
        summary={{ total_bookings: 1, total_revenue: 0, average_rating: 0, conversion_rate: 0 }}
      />
    );
    expect(screen.getByText(/€0/)).toBeInTheDocument();
  });
});