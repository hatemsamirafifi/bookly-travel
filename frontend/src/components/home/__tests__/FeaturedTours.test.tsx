import { render, screen } from '@testing-library/react';
import FeaturedTours from '../FeaturedTours';
import type { TourCard as TourCardType } from '@/lib/api/types';

jest.mock('next-intl/server', () => ({
  getTranslations: async () => (key: string) => key,
}));

jest.mock('@/components/search/TourCard', () => ({
  __esModule: true,
  default: ({ tour, imagePriority }: { tour: TourCardType; imagePriority?: boolean }) => (
    <div data-testid="featured-card" data-image-priority={String(imagePriority === true)}>{tour.title}</div>
  ),
}));

describe('FeaturedTours', () => {
  it('prioritizes only the first featured cover, leaving the rest deferred', async () => {
    const tours = [1, 2, 3].map((id) => ({ id, title: `Tour ${id}` } as TourCardType));
    render(await FeaturedTours({ tours, locale: 'en' }));
    const cards = screen.getAllByTestId('featured-card');
    expect(cards[0]).toHaveAttribute('data-image-priority', 'true');
    expect(cards[1]).toHaveAttribute('data-image-priority', 'false');
    expect(cards[2]).toHaveAttribute('data-image-priority', 'false');
  });

  it('does not render or request a cover for an empty featured list', async () => {
    expect(await FeaturedTours({ tours: [], locale: 'en' })).toBeNull();
  });
});
