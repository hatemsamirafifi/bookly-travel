import { fireEvent, render, screen } from '@testing-library/react';
import { PartnerHeader } from '@/components/partner/layout/PartnerHeader';
import { PartnerSidebar } from '@/components/partner/layout/PartnerSidebar';
import messages from '../../../../messages/es.json';

jest.mock('@/lib/hooks/useAuth', () => ({ useAuth: () => ({ user: { name: 'Alex' }, logout: jest.fn() }) }));
jest.mock('@/components/partner/layout/NotificationBell', () => ({ NotificationBell: () => null }));
jest.mock('next/navigation', () => ({ usePathname: () => '/es/partner/tours' }));
jest.mock('next-intl', () => ({ useTranslations: (namespace: string) => (key: string) => {
  const section = namespace.split('.').reduce((value: unknown, part) => (value as Record<string, unknown>)[part], messages);
  return (section as Record<string, string>)[key] ?? key;
} }));

it('preserves the active locale in every partner navigation link', () => {
  render(<PartnerSidebar />);
  for (const link of screen.getAllByRole('link')) expect(link.getAttribute('href')).toMatch(/^\/es\//);
  expect(screen.getByRole('link', { name: messages.partner.nav.tours })).toHaveAttribute('aria-current', 'page');
});

it('names the partner account action and restores focus on Escape', () => {
  render(<PartnerHeader onMenuClick={jest.fn()} />);
  const trigger = screen.getByRole('button', { name: messages.nav.accountMenu });
  trigger.focus();
  fireEvent.click(trigger);
  expect(screen.getByRole('menuitem', { name: messages.partner.header.logout })).toHaveFocus();
  fireEvent.keyDown(screen.getByRole('menu'), { key: 'Escape' });
  expect(screen.queryByRole('menu')).not.toBeInTheDocument();
  expect(trigger).toHaveFocus();
});
