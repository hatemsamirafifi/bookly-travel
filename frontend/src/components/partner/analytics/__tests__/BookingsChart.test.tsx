import { render, screen } from '@testing-library/react';
import { BookingsChart } from '../BookingsChart';
import type { AnalyticsBookingsPoint } from '@/types/partner';

describe('BookingsChart', () => {
  it('renders the title', () => {
    render(<BookingsChart data={[]} />);
    expect(screen.getByText('bookingsOverTime')).toBeInTheDocument();
  });

  it('shows an empty-state message when there is no data', () => {
    render(<BookingsChart data={[]} />);
    expect(screen.getByText('noData')).toBeInTheDocument();
  });

  it('hides the empty-state message when data is present', () => {
    const data: AnalyticsBookingsPoint[] = [
      { date: '2026-06-01', bookings: 1, revenue: 100 },
      { date: '2026-06-02', bookings: 2, revenue: 200 },
    ];
    render(<BookingsChart data={data} />);
    expect(screen.queryByText('noData')).not.toBeInTheDocument();
    expect(screen.getByText('bookingsOverTime')).toBeInTheDocument();
  });
});