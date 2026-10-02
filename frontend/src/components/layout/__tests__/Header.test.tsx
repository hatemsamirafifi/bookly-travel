import { fireEvent, render, screen } from '@testing-library/react';
import Header from '../Header';
import { useAuth } from '@/lib/hooks/useAuth';
import messages from '../../../../messages/en.json';

jest.mock('@/lib/hooks/useAuth', () => ({ useAuth: jest.fn() }));
jest.mock('@/i18n/routing', () => ({ locales: ['en', 'es', 'it'] }));
jest.mock('next/navigation', () => ({ useParams: () => ({ locale: 'en' }), usePathname: () => '/en', useSearchParams: () => new URLSearchParams() }));
jest.mock('next-intl', () => ({ useTranslations: (namespace: string) => (key: string) => {
  const section = namespace.split('.').reduce((value: unknown, part) => (value as Record<string, unknown>)[part], messages);
  return (section as Record<string, string>)[key] ?? key;
} }));

beforeEach(() => {
  (useAuth as jest.Mock).mockReturnValue({ user: null, isLoading: false, logout: jest.fn() });
});

it('offers localized expandable search and a distinct tours-only discovery row', () => {
  render(<Header locale="en" />);
  fireEvent.click(screen.getByRole('button', { name: 'Search tours' }));
  expect(screen.getByRole('searchbox', { name: 'Search tours' })).toHaveFocus();
  const search = screen.getByRole('search');
  expect(search).toHaveAttribute('action', '/en/search');
  expect(screen.getByRole('searchbox')).toHaveAttribute('name', 'q');
  expect(screen.getByRole('navigation', { name: 'Discover tours' })).toBeInTheDocument();
  expect(screen.queryByRole('link', { name: /hotels|restaurants|rewards/i })).not.toBeInTheDocument();
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
