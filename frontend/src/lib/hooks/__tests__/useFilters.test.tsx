import { renderHook, act } from '@testing-library/react';
import { useFilters } from '@/lib/hooks/useFilters';

// F3 regression: filter controls must navigate within the current listing
// page (derived from usePathname) rather than always jumping to /search.

const mockPush = jest.fn();
let mockPathname = '/en/search';
let mockSearchParams = new URLSearchParams();

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: (...args: unknown[]) => mockPush(...args) }),
  useSearchParams: () => mockSearchParams,
  useParams: () => ({ locale: 'en' }),
  usePathname: () => mockPathname,
}));

describe('useFilters', () => {
  beforeEach(() => {
    mockPush.mockClear();
    mockPathname = '/en/search';
    mockSearchParams = new URLSearchParams();
  });

  it('navigates within the search page', () => {
    mockPathname = '/en/search';
    const { result } = renderHook(() => useFilters());

    act(() => result.current.setFilter('category', 'adventure'));

    expect(mockPush).toHaveBeenCalledWith(
      '/en/search?category=adventure',
      { scroll: false }
    );
  });

  it('keeps category context when sorting on a category listing page', () => {
    mockPathname = '/en/categories/adventure';
    const { result } = renderHook(() => useFilters());

    act(() => result.current.setFilter('sort', 'price_asc'));

    expect(mockPush).toHaveBeenCalledWith(
      '/en/categories/adventure?sort=price_asc',
      { scroll: false }
    );
  });

  it('keeps destination context when changing page on a destination listing', () => {
    mockPathname = '/en/destinations/rome';
    mockSearchParams = new URLSearchParams('category=food-wine');
    const { result } = renderHook(() => useFilters());

    act(() => result.current.setFilter('page', '2'));

    // Changing page keeps the existing category param and stays on /destinations/rome.
    expect(mockPush).toHaveBeenCalledWith(
      expect.stringContaining('/en/destinations/rome'),
      { scroll: false }
    );
    expect(mockPush.mock.calls[0][0]).toContain('category=food-wine');
    expect(mockPush.mock.calls[0][0]).toContain('page=2');
  });

  it('clearAll stays on the current listing page', () => {
    mockPathname = '/en/categories/adventure';
    mockSearchParams = new URLSearchParams('sort=price_asc&page=3');
    const { result } = renderHook(() => useFilters());

    act(() => result.current.clearAll());

    expect(mockPush).toHaveBeenCalledWith('/en/categories/adventure', { scroll: false });
  });
});