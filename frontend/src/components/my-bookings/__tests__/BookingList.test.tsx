import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import BookingList from '../BookingList';
import { getMyBookings, getMyBookingsSummary } from '@/lib/api/my-bookings';
import type { TravelerBooking } from '@/types/traveler';

jest.mock('@/lib/api/my-bookings', () => ({
  getMyBookings: jest.fn(),
  getMyBookingsSummary: jest.fn(),
}));

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn() }),
  useSearchParams: () => new URLSearchParams(),
}));

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) => {
    const translations: Record<string, string> = {
      summaryLabel: 'Booking summary',
      summaryTotal: 'Total bookings',
      summaryUpcoming: 'Upcoming',
      summaryCompleted: 'Completed',
      summaryCancelled: 'Cancelled',
      recentActivity: 'Recent activity',
      quickActions: 'Quick actions',
      browseTours: 'Browse Tours',
      viewWishlist: 'View Wishlist',
      editProfile: 'Edit Profile',
      noRecentActivity: 'No recent activity',
      empty: 'No bookings yet',
      loadError: 'Failed to load bookings',
      fallbackTour: 'Unknown Tour',
    };
    // Handle nested keys like status.confirmed
    return translations[key] || key;
  },
}));

const bookings: TravelerBooking[] = [
  {
    id: '1',
    reference: 'BKO-1',
    status: 'confirmed' as const,
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
    status: 'completed' as const,
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

function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        gcTime: 0,
      },
    },
  });
}

function renderWithProviders(ui: React.ReactElement) {
  const queryClient = createTestQueryClient();
  return render(
    <QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>
  );
}

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
    jest.mocked(getMyBookingsSummary).mockResolvedValue({
      data: {
        total: 2,
        confirmed: 1,
        completed: 1,
        cancelled: 0,
        no_show: 0,
      },
    });
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('renders summary counts, recent activity, and quick actions', async () => {
    renderWithProviders(<BookingList locale="en" />);

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
