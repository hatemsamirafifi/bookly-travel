import { render, screen, fireEvent } from '@testing-library/react';
import MobileFilterDrawer from '../MobileFilterDrawer';
import type { SearchResponse } from '@/lib/api/types';

const mockSetMultipleFilters = jest.fn();
let mockFilters: Record<string, string> = {};

jest.mock('@/lib/hooks/useFilters', () => ({
  useFilters: () => ({
    filters: mockFilters,
    setMultipleFilters: mockSetMultipleFilters,
    activeFilterCount: Object.keys(mockFilters).length,
  }),
}));

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string, values?: { count?: number }) => {
    const messages: Record<string, string> = {
      filters: 'Filters',
      reset: 'Reset',
      closeFilters: 'Close filters',
      category: 'Category',
      priceRange: 'Price Range',
      minPrice: 'Min Price',
      maxPrice: 'Max Price',
      duration: 'Duration',
      filterPanelLabel: 'Filter search results',
    };
    if (key === 'showToursCount') {
      return `Show ${values?.count ?? 0} Tours`;
    }
    return messages[key] || key;
  },
}));

const mockFilterData: SearchResponse['filters'] = {
  categories: [
    { slug: 'walking', name: 'Walking Tours', count: 12 },
    { slug: 'beach-water', name: 'Beach & Water', count: 5 },
  ],
  locations: [],
  price_range: { min: 10, max: 200 },
  durations: [
    { value: '2-4', label: '2 to 4 hours', count: 9 },
  ],
};

describe('MobileFilterDrawer', () => {
  beforeEach(() => {
    mockSetMultipleFilters.mockClear();
    mockFilters = {};
  });

  it('renders trigger button with filter count', () => {
    mockFilters = { category: 'walking' };
    render(<MobileFilterDrawer filterData={mockFilterData} totalResults={15} />);

    expect(screen.getByRole('button', { name: /filters \(1\)/i })).toBeInTheDocument();
  });

  it('opens drawer on button click and stages filter changes without premature apply', () => {
    render(<MobileFilterDrawer filterData={mockFilterData} totalResults={15} />);

    const openBtn = screen.getByRole('button', { name: /filters/i });
    fireEvent.click(openBtn);

    expect(screen.getByRole('dialog')).toBeInTheDocument();

    // Select category in drawer
    const walkingRadio = screen.getByLabelText(/Walking Tours/i);
    fireEvent.click(walkingRadio);

    // Staged: setMultipleFilters should NOT have been called yet
    expect(mockSetMultipleFilters).not.toHaveBeenCalled();
  });

  it('commits staged filters and closes drawer when Show [N] Tours is clicked', () => {
    render(<MobileFilterDrawer filterData={mockFilterData} totalResults={12} />);

    // Open drawer
    fireEvent.click(screen.getByRole('button', { name: /filters/i }));

    // Select walking category
    fireEvent.click(screen.getByLabelText(/Walking Tours/i));

    // Click confirm button
    const confirmBtn = screen.getByRole('button', { name: /show 12 tours/i });
    fireEvent.click(confirmBtn);

    expect(mockSetMultipleFilters).toHaveBeenCalledWith(
      expect.objectContaining({
        category: 'walking',
      })
    );

    // Dialog should now be closed
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('resets staged filters when Reset is clicked', () => {
    mockFilters = { category: 'beach-water' };
    render(<MobileFilterDrawer filterData={mockFilterData} totalResults={5} />);

    fireEvent.click(screen.getByRole('button', { name: /filters/i }));

    const resetBtn = screen.getByRole('button', { name: /reset/i });
    fireEvent.click(resetBtn);

    // Now click confirm button to verify reset was staged
    const confirmBtn = screen.getByRole('button', { name: /show 5 tours/i });
    fireEvent.click(confirmBtn);

    expect(mockSetMultipleFilters).toHaveBeenCalledWith(
      expect.objectContaining({
        category: null,
      })
    );
  });
});

