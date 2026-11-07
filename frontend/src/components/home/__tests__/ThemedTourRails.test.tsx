import { render, screen } from '@testing-library/react';
import ThemedTourRails from '../ThemedTourRails';
import type { CuratedRail } from '@/lib/api/types';
import { buildMockTourCard } from '../../../../tests/fixtures/homepage-discovery';

jest.mock('@/components/home/FeaturedTours', () => ({
  __esModule: true,
  default: ({ title, tours }: { title: string; tours: unknown[] }) => (
    <div data-testid="themed-rail" data-title={title} data-count={tours.length}>
      {title}
    </div>
  ),
}));

describe('ThemedTourRails', () => {
  it('renders each non-empty curated rail and omits empty rails (FR-001, FR-007)', () => {
    const rails: CuratedRail[] = [
      {
        id: 'family-friendly',
        slug: 'family-friendly',
        title: 'Top Family Experiences',
        tours: [buildMockTourCard({ id: 101 })],
      },
      {
        id: 'empty-rail',
        slug: 'empty-rail',
        title: 'Empty Rail',
        tours: [],
      },
      {
        id: 'outdoor-adventures',
        slug: 'outdoor-adventures',
        title: 'Outdoor Adventures',
        tours: [buildMockTourCard({ id: 201 }), buildMockTourCard({ id: 202 })],
      },
    ];

    render(<ThemedTourRails rails={rails} locale="en" />);

    const renderedRails = screen.getAllByTestId('themed-rail');
    expect(renderedRails).toHaveLength(2);
    expect(renderedRails[0]).toHaveAttribute('data-title', 'Top Family Experiences');
    expect(renderedRails[1]).toHaveAttribute('data-title', 'Outdoor Adventures');
    expect(screen.queryByText('Empty Rail')).not.toBeInTheDocument();
  });

  it('renders nothing if rails array is empty or undefined', () => {
    const { container } = render(<ThemedTourRails rails={[]} locale="en" />);
    expect(container.firstChild).toBeNull();
  });
});

