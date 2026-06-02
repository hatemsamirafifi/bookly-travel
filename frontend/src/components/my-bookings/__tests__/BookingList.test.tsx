import { render, screen, waitFor } from '@testing-library/react';
import BookingList from '../BookingList';
import { getMyBookings } from '@/lib/api/my-bookings';

jest.mock('@/lib/api/my-bookings', () => ({
  getMyBookings: jest.fn(),
}));

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn() }),
  useSearchParams: () => new URLSearchParams(),
}));

const bookings = [
  {
    id: '1',
    reference: 'BKO-1',
    status: 'confirmed',
    tour_date: '2026-07-01',
    created_at: '2026-05-01T10:00:00Z',
    participants: 2,
    total_amount: 12000,
    tour: {
      id: 'tour-1',
      name: 'Rome Food Walk',
      slug: 'rome-food-walk',
      location: 'Rome',
    },
  },
  {
    id: '2',
    reference: 'BKO-2',
    status: 'completed',
    tour_date: '2026-04-01',
    created_at: '2026-05-02T10:00:00Z',
    participants: 1,
    total_amount: 9000,
    tour: {
      id: 'tour-2',
      name: 'Paris Museum Pass',
      slug: 'paris-museum-pass',
      location: 'Paris',
    },
  },
];

describe('BookingList', () => {
  beforeEach(() => {
    jest.mocked(getMyBookings).mockResolvedValue({
      data: bookings,
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 12,
        total: bookings.length,
      },
    });
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('renders summary counts, recent activity, and quick actions', async () => {
    render(<BookingList locale="en" />);

    await waitFor(() => expect(screen.getAllByText('Rome Food Walk').length).toBeGreaterThan(0));

    expect(screen.getByText('Total bookings')).toBeInTheDocument();
    expect(screen.getByText('Upcoming')).toBeInTheDocument();
    expect(screen.getByText('Completed')).toBeInTheDocument();
    expect(screen.getByText('Recent activity')).toBeInTheDocument();
    expect(screen.getByText('Quick actions')).toBeInTheDocument();
    expect(screen.getByText('Browse Tours')).toBeInTheDocument();
    expect(screen.getByText('View Wishlist')).toBeInTheDocument();
    expect(screen.getByText('Edit Profile')).toBeInTheDocument();
  });
});
