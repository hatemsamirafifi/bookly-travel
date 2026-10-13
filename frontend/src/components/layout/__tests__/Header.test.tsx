import { fireEvent, render, screen, within } from '@testing-library/react';
import { usePathname } from 'next/navigation';
import Header from '../Header';
import { useAuth } from '@/lib/hooks/useAuth';
import messages from '../../../../messages/en.json';

jest.mock('@/lib/hooks/useAuth', () => ({ useAuth: jest.fn() }));
jest.mock('@/i18n/routing', () => ({ locales: ['en', 'es', 'it'] }));
jest.mock('next/navigation', () => ({ useParams: () => ({ locale: 'en' }), usePathname: jest.fn(), useSearchParams: () => new URLSearchParams() }));
jest.mock('next-intl', () => ({ useTranslations: (namespace: string) => (key: string) => {
  const section = namespace.split('.').reduce((value: unknown, part) => (value as Record<string, unknown>)[part], messages);
  return (section as Record<string, string>)[key] ?? key;
} }));

beforeEach(() => {
  (useAuth as jest.Mock).mockReturnValue({ user: null, isLoading: false, logout: jest.fn() });
  (usePathname as jest.Mock).mockReturnValue('/en');
});

it('offers one navigation with each primary destination once', () => {
  render(<Header locale="en" />);
  const navigation = screen.getByRole('navigation', { name: 'Main navigation' });
  expect(screen.getAllByRole('navigation')).toHaveLength(1);
  expect(within(navigation).getAllByRole('link').map((link) => link.getAttribute('href')))
    .toEqual(['/en', '/en/search', '/en/categories', '/en/destinations']);
  expect(screen.queryByRole('link', { name: /hotels|restaurants|rewards/i })).not.toBeInTheDocument();
});

it('opens localized search and restores focus when Escape closes it', () => {
  render(<Header locale="en" />);
  const trigger = screen.getByRole('button', { name: 'Search tours' });
  expect(screen.queryByRole('searchbox')).not.toBeInTheDocument();
  fireEvent.click(trigger);
  expect(screen.getByRole('searchbox', { name: 'Search tours' })).toHaveFocus();
  const search = screen.getByRole('search');
  expect(search).toHaveAttribute('action', '/en/search');
  expect(screen.getByRole('searchbox')).toHaveAttribute('name', 'q');
  fireEvent.keyDown(screen.getByRole('searchbox'), { key: 'Escape' });
  expect(screen.queryByRole('searchbox')).not.toBeInTheDocument();
  expect(trigger).toHaveFocus();
});

it.each(['/en', '/en/search', '/en/categories', '/en/destinations'])('identifies the current navigation destination at %s', (pathname) => {
  (usePathname as jest.Mock).mockReturnValue(pathname);
  render(<Header locale="en" />);
  const navigation = screen.getByRole('navigation', { name: 'Main navigation' });
  expect(within(navigation).getByRole('link', { current: 'page' })).toHaveAttribute('href', pathname);
});

it.each(['traveler', 'partner'])('names the %s account action and returns focus after Escape', (role) => {
  (useAuth as jest.Mock).mockReturnValue({ user: { name: 'Alex', role }, isLoading: false, logout: jest.fn() });
  render(<Header locale="en" />);
  const trigger = screen.getByRole('button', { name: 'Account menu' });
  trigger.focus();
  fireEvent.click(trigger);
  expect(screen.getByRole('menuitem', { name: 'Dashboard' })).toHaveFocus();
  expect(screen.getByRole('menuitem', { name: 'Dashboard' })).toHaveAttribute('href', role === 'partner' ? '/en/partner' : '/en/my-bookings');
  fireEvent.keyDown(screen.getByRole('menu'), { key: 'Escape' });
  expect(screen.queryByRole('menu')).not.toBeInTheDocument();
  expect(trigger).toHaveFocus();
});
