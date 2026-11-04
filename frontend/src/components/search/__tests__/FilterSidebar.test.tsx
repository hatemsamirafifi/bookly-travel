import { render, screen, fireEvent } from '@testing-library/react';
import FilterSidebar from '../FilterSidebar';
import type { SearchResponse } from '@/lib/api/types';

const mockSetFilter = jest.fn();
const mockClearAll = jest.fn();
let mockFilters: Record<string, string> = {};

jest.mock('@/lib/hooks/useFilters', () => ({
  useFilters: () => ({
    filters: mockFilters,
    setFilter: mockSetFilter,
    clearAll: mockClearAll,
    activeFilterCount: Object.keys(mockFilters).length,
  }),
}));

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) => {
    const messages: Record<string, string> = {
      filters: 'Filters',
      clearAll: 'Clear all',
      category: 'Category',
      priceRange: 'Price Range',
      minPrice: 'Min Price',
      maxPrice: 'Max Price',
      duration: 'Duration',
      availableDate: 'Available Date',
      filterPanelLabel: 'Filter search results',
    };
    return messages[key] || key;
  },
}));

const mockFilterData: SearchResponse['filters'] = {
  categories: [
    { slug: 'walking', name: 'Walking Tours', count: 12 },
    { slug: 'museums', name: 'Museums & Art', count: 8 },
  ],
  locations: [
    { slug: 'rome', name: 'Rome', count: 15 },
  ],
  price_range: { min: 10, max: 250 },
  durations: [
    { value: '2-4', label: '2 to 4 hours', count: 9 },
  ],
};

describe('FilterSidebar', () => {
  beforeEach(() => {
    mockSetFilter.mockClear();
    mockClearAll.mockClear();
    mockFilters = {};
  });

  it('renders filter categories, price range, and durations', () => {
    render(<FilterSidebar filterData={mockFilterData} />);

    expect(screen.getByText('Walking Tours')).toBeInTheDocument();
    expect(screen.getByText('Museums & Art')).toBeInTheDocument();
    expect(screen.getByText('2 to 4 hours')).toBeInTheDocument();
  });

  it('triggers setFilter when a category radio/checkbox is clicked', () => {
    render(<FilterSidebar filterData={mockFilterData} />);

    const walkingRadio = screen.getByLabelText(/Walking Tours/i);
    fireEvent.click(walkingRadio);

    expect(mockSetFilter).toHaveBeenCalledWith('category', 'walking');
  });

  it('triggers setFilter when min price is changed', () => {
    render(<FilterSidebar filterData={mockFilterData} />);

    const minPriceInput = screen.getByLabelText('Min Price');
    fireEvent.change(minPriceInput, { target: { value: '50' } });

    expect(mockSetFilter).toHaveBeenCalledWith('price_min', '50');
  });

  it('triggers setFilter when duration is selected', () => {
    render(<FilterSidebar filterData={mockFilterData} />);

    const durationRadio = screen.getByLabelText(/2 to 4 hours/i);
    fireEvent.click(durationRadio);

    expect(mockSetFilter).toHaveBeenCalledWith('duration', '2-4');
  });
});

