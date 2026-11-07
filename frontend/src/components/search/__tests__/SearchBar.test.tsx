import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import SearchBar from '../SearchBar';

const mockPush = jest.fn();
let mockSearchParams = new URLSearchParams();
let mockParams = { locale: 'en' };

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: mockPush }),
  useParams: () => mockParams,
  useSearchParams: () => mockSearchParams,
}));

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) => {
    const messages: Record<string, string> = {
      searchTours: 'Search tours',
      placeholder: 'Search destinations or activities...',
      searchButton: 'Search',
      loading: 'Loading...',
    };
    return messages[key] || key;
  },
}));

describe('SearchBar Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockSearchParams = new URLSearchParams();
    mockParams = { locale: 'en' };
  });

  it('renders form with progressive enhancement action, method and name attribute', () => {
    render(<SearchBar />);
    const form = screen.getByRole('search');
    expect(form).toHaveAttribute('action', '/en/search');
    expect(form).toHaveAttribute('method', 'GET');

    const input = screen.getByRole('searchbox', { name: 'Search tours' });
    expect(input).toHaveAttribute('name', 'q');
    expect(input).toHaveAttribute('id', 'search-input');
  });

  it('submits search query and navigates to locale search page', () => {
    render(<SearchBar />);
    const input = screen.getByRole('searchbox', { name: 'Search tours' });
    fireEvent.change(input, { target: { value: 'Rome' } });
    fireEvent.submit(screen.getByRole('search'));

    expect(mockPush).toHaveBeenCalledWith('/en/search?q=Rome');
  });

  it('reads submitted value directly from form data to prevent stale state synchronization', () => {
    render(<SearchBar initialQuery="" />);
    const input = screen.getByRole('searchbox', { name: 'Search tours' }) as HTMLInputElement;

    // Simulate instant DOM input and submission in the same tick
    input.value = 'Venice';
    fireEvent.submit(screen.getByRole('search'));

    expect(mockPush).toHaveBeenCalledWith('/en/search?q=Venice');
  });

  it('trims whitespace and deletes q when query is whitespace only', () => {
    mockSearchParams = new URLSearchParams('category=walking');
    render(<SearchBar />);
    const input = screen.getByRole('searchbox', { name: 'Search tours' });
    fireEvent.change(input, { target: { value: '   ' } });
    fireEvent.submit(screen.getByRole('search'));

    expect(mockPush).toHaveBeenCalledWith('/en/search?category=walking');
  });

  it('preserves existing search parameters while resetting page parameter', () => {
    mockSearchParams = new URLSearchParams('category=adventure&sort=price_asc&page=3');
    render(<SearchBar />);
    const input = screen.getByRole('searchbox', { name: 'Search tours' });
    fireEvent.change(input, { target: { value: 'Florence' } });
    fireEvent.submit(screen.getByRole('search'));

    expect(mockPush).toHaveBeenCalledWith('/en/search?category=adventure&sort=price_asc&q=Florence');
  });

  it('supports Spanish locale route navigation', () => {
    mockParams = { locale: 'es' };
    render(<SearchBar />);
    const form = screen.getByRole('search');
    expect(form).toHaveAttribute('action', '/es/search');

    const input = screen.getByRole('searchbox', { name: 'Search tours' });
    fireEvent.change(input, { target: { value: 'Madrid' } });
    fireEvent.submit(form);

    expect(mockPush).toHaveBeenCalledWith('/es/search?q=Madrid');
  });
});
