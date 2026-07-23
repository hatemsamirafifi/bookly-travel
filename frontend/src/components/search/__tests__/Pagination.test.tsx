import { render, screen, fireEvent } from '@testing-library/react';
import Pagination from '../Pagination';

// F3 regression: pagination must stay on the current listing page
// (search / category / destination) rather than always jumping to /search.

const mockPush = jest.fn();
let mockPathname = '/en/search';
let mockSearchParams = new URLSearchParams();

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: (...args: unknown[]) => mockPush(...args) }),
  useSearchParams: () => mockSearchParams,
  usePathname: () => mockPathname,
}));

describe('Pagination', () => {
  beforeEach(() => {
    mockPush.mockClear();
    mockPathname = '/en/search';
    mockSearchParams = new URLSearchParams();
  });

  it('renders nothing when there is only one page', () => {
    const { container } = render(<Pagination currentPage={1} lastPage={1} />);
    expect(container).toBeEmptyDOMElement();
  });

  it('navigates to the next page on the search page', () => {
    mockPathname = '/en/search';
    render(<Pagination currentPage={1} lastPage={3} />);

    fireEvent.click(screen.getByRole('button', { name: 'Next page' }));

    expect(mockPush).toHaveBeenCalledWith('/en/search?page=2');
  });

  it('stays on the category listing when paging (F3)', () => {
    mockPathname = '/en/categories/adventure';
    mockSearchParams = new URLSearchParams('sort=price_asc');
    render(<Pagination currentPage={2} lastPage={5} />);

    fireEvent.click(screen.getByRole('button', { name: 'Next page' }));

    expect(mockPush.mock.calls[0][0]).toContain('/en/categories/adventure');
    expect(mockPush.mock.calls[0][0]).toContain('page=3');
    expect(mockPush.mock.calls[0][0]).toContain('sort=price_asc');
  });

  it('stays on the destination listing when paging (F3)', () => {
    mockPathname = '/en/destinations/rome';
    render(<Pagination currentPage={1} lastPage={4} />);

    fireEvent.click(screen.getByRole('button', { name: 'Next page' }));

    expect(mockPush).toHaveBeenCalledWith('/en/destinations/rome?page=2');
  });
});