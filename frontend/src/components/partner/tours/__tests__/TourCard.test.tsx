import { render, screen, fireEvent } from '@testing-library/react';
import { TourCard } from '../TourCard';
import type { Tour } from '@/types/partner';

jest.mock('next/image', () => ({
  __esModule: true,
  default: ({ src, alt, ...rest }: { src: string; alt: string }) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt={alt} {...rest} />
  ),
}));

const archiveTour = jest.fn().mockResolvedValue({ data: {} });
jest.mock('@/lib/api/partner', () => ({
  ...jest.requireActual('@/lib/api/partner'),
  archiveTour: (id: string | number) => archiveTour(id),
}));

function makeTour(overrides: Partial<Tour> = {}): Tour {
  return {
    id: '1',
    partner_id: 'p1',
    title: 'Tuscan Wine Tasting',
    slug: 'tuscan-wine-tasting',
    description: 'desc',
    category: 'food',
    destination: 'Tuscany',
    location: 'Tuscany',
    duration: { minutes: 480, label: '8 hours' },
    meeting_point: 'Piazza',
    highlights: [],
    inclusions: [],
    exclusions: [],
    cancellation_policy: '',
    languages: [],
    status: 'published',
    media: [],
    pricing_tiers: [{ id: '1', name: 'Adult', price: 10, currency: 'EUR', min_participants: 1, max_participants: 10 }],
    availability_rules: [],
    availability_exceptions: [],
    min_participants: 1,
    max_participants: 10,
    ...overrides,
  } as unknown as Tour;
}

describe('TourCard', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    window.confirm = jest.fn(() => true);
  });

  it('renders the tour title and status badge', () => {
    render(<TourCard tour={makeTour({ status: 'published' })} />);
    expect(screen.getByText('Tuscan Wine Tasting')).toBeInTheDocument();
    expect(screen.getByText('Published')).toBeInTheDocument();
  });

  it('shows the cover-image placeholder when no cover media exists', () => {
    render(<TourCard tour={makeTour({ media: [] })} />);
    expect(screen.getByText('No cover image')).toBeInTheDocument();
  });

  it('renders the cover image when cover media exists', () => {
    render(
      <TourCard
        tour={makeTour({
          media: [{ id: 'm1', url: 'https://example.com/cover.png', is_cover: true, sort_order: 0 }],
        })}
      />
    );
    expect(screen.getByAltText('Tuscan Wine Tasting')).toBeInTheDocument();
    expect(screen.queryByText('No cover image')).not.toBeInTheDocument();
  });

  it('displays the minimum tier price', () => {
    render(
      <TourCard
        tour={makeTour({
          pricing_tiers: [
            { id: '1', name: 'Adult', price: 25, currency: 'EUR', min_participants: 1, max_participants: 10 },
            { id: '2', name: 'Child', price: 10, currency: 'EUR', min_participants: 1, max_participants: 10 },
          ],
        })}
      />
    );
    expect(screen.getByText('€10.00')).toBeInTheDocument();
  });

  it('shows an explanatory hint for rejected tours but not published ones', () => {
    const { rerender } = render(<TourCard tour={makeTour({ status: 'published' })} />);
    expect(screen.queryByText('Needs revision before resubmitting.')).not.toBeInTheDocument();
    rerender(<TourCard tour={makeTour({ status: 'rejected' })} />);
    expect(screen.getByText('Needs revision before resubmitting.')).toBeInTheDocument();
  });

  it('does not render the archive button for already-archived tours', () => {
    render(<TourCard tour={makeTour({ status: 'archived' })} />);
    expect(screen.queryByLabelText('Archive tour')).not.toBeInTheDocument();
  });

  it('archives the tour via the API after confirmation and calls onArchived', () => {
    const onArchived = jest.fn();
    render(<TourCard tour={makeTour({ status: 'published' })} onArchived={onArchived} />);
    fireEvent.click(screen.getByLabelText('Archive tour'));
    expect(window.confirm).toHaveBeenCalled();
    expect(archiveTour).toHaveBeenCalledWith('1');
  });

  it('does not archive when the confirmation is dismissed', () => {
    window.confirm = jest.fn(() => false);
    render(<TourCard tour={makeTour({ status: 'published' })} />);
    fireEvent.click(screen.getByLabelText('Archive tour'));
    expect(archiveTour).not.toHaveBeenCalled();
  });
});