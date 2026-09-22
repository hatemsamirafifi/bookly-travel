import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { BookingsChart } from '../BookingsChart';
import type { AnalyticsBookingsPoint } from '@/types/partner';

interface MockYAxisProps {
  yAxisId?: number;
  tickFormatter?: (value: number) => string;
}

type MockTooltipFormatter = (value: number, name: string) => [unknown, string];

let mockYAxisProps: MockYAxisProps[] = [];
let mockTooltipFormatter: MockTooltipFormatter | undefined;

jest.mock('recharts', () => ({
  ResponsiveContainer: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  LineChart: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  Line: () => null,
  XAxis: () => null,
  YAxis: (props: MockYAxisProps) => {
    mockYAxisProps.push(props);
    return null;
  },
  CartesianGrid: () => null,
  Tooltip: ({ formatter }: { formatter?: MockTooltipFormatter }) => {
    mockTooltipFormatter = formatter;
    return null;
  },
}));

describe('BookingsChart', () => {
  beforeEach(() => {
    mockYAxisProps = [];
    mockTooltipFormatter = undefined;
  });

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

  it('formats chart revenue from minor units in axes and tooltips', () => {
    render(<BookingsChart data={[{ date: '2026-06-01', bookings: 1, revenue: 12345 }]} />);

    const revenueAxis = mockYAxisProps.find((props) => props.yAxisId === 1);
    expect(revenueAxis?.tickFormatter?.(12345)).toMatch(/€123\.45/);
    expect(mockTooltipFormatter?.(12345, 'revenue')[0]).toMatch(/€123\.45/);
  });
});
