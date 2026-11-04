import { render, screen, fireEvent } from '@testing-library/react';
import ActiveFilterChips from '../ActiveFilterChips';

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
      clearAll: 'Clear all',
      activeFilters: 'Active filters',
    };
    return messages[key] || key;
  },
}));

describe('ActiveFilterChips', () => {
  beforeEach(() => {
    mockSetFilter.mockClear();
    mockClearAll.mockClear();
    mockFilters = {};
  });

  it('renders nothing when no filters are active', () => {
    const { container } = render(<ActiveFilterChips />);
    expect(container).toBeEmptyDOMElement();
  });

  it('renders chips for active category and duration filters', () => {
    mockFilters = { category: 'walking', duration: '2-4' };
    render(<ActiveFilterChips />);

    expect(screen.getByText(/walking/i)).toBeInTheDocument();
    expect(screen.getByText(/2-4/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /clear all/i })).toBeInTheDocument();
  });

  it('calls setFilter with null when a chip remove button is clicked', () => {
    mockFilters = { category: 'walking' };
    render(<ActiveFilterChips />);

    const removeBtn = screen.getByRole('button', { name: /remove walking filter/i });
    fireEvent.click(removeBtn);

    expect(mockSetFilter).toHaveBeenCalledWith('category', null);
  });

  it('calls clearAll when Clear all button is clicked', () => {
    mockFilters = { category: 'walking', price_min: '50' };
    render(<ActiveFilterChips />);

    const clearAllBtn = screen.getByRole('button', { name: /clear all/i });
    fireEvent.click(clearAllBtn);

    expect(mockClearAll).toHaveBeenCalledTimes(1);
  });
});

