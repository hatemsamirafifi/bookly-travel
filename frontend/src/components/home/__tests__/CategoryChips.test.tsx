import { render, screen } from '@testing-library/react';
import CategoryChips from '../CategoryChips';
import type { Category } from '@/lib/api/types';

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) => key,
}));

describe('CategoryChips', () => {
  const mockCategories: Category[] = [
    { slug: 'walking-tours', name: 'Walking Tours', tour_count: 12 },
    { slug: 'food-and-drink', name: 'Food & Drink', tour_count: 8 },
  ];

  it('renders category chips linking directly to filtered search (FR-005)', () => {
    render(<CategoryChips categories={mockCategories} locale="en" />);

    const walkingLink = screen.getByRole('link', { name: /Walking Tours/i });
    expect(walkingLink).toHaveAttribute('href', '/en/search?category=walking-tours');

    const foodLink = screen.getByRole('link', { name: /Food & Drink/i });
    expect(foodLink).toHaveAttribute('href', '/en/search?category=food-and-drink');
  });

  it('renders nothing when categories array is empty', () => {
    const { container } = render(<CategoryChips categories={[]} locale="en" />);
    expect(container.firstChild).toBeNull();
  });
});

