import { render, screen, fireEvent } from '@testing-library/react';
import { NextIntlClientProvider } from 'next-intl';
import { AvailabilityCalendar } from '../AvailabilityCalendar';
import { useTourWizardStore } from '@/lib/stores/tourWizard';
import en from '../../../../../messages/en.json';
import es from '../../../../../messages/es.json';
import itMessages from '../../../../../messages/it.json';

jest.mock('next-intl', () => jest.requireActual('@/test-utils/catalogIntl'));

type Locale = 'en' | 'es' | 'it';
const catalogs = { en, es, it: itMessages };

describe.each(['en', 'es', 'it'] as const)('AvailabilityCalendar locale %s', (locale: Locale) => {
  const messages = catalogs[locale];
  const tAvail = messages.partner.tours.availability;

  beforeEach(() => {
    localStorage.clear();
    useTourWizardStore.getState().reset();
  });

  function renderCalendar() {
    render(
      <NextIntlClientProvider locale={locale} messages={messages} timeZone="UTC">
        <AvailabilityCalendar />
      </NextIntlClientProvider>
    );
  }

  it('renders localized empty states, controls, and preserves canonical store enums', () => {
    renderCalendar();

    expect(screen.getByText(tAvail.recurringSchedule)).toBeInTheDocument();
    expect(screen.getByText(tAvail.noRulesYet)).toBeInTheDocument();
    expect(screen.getByText(tAvail.noExceptionsYet)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: tAvail.addRule }));
    expect(useTourWizardStore.getState().formData.availability_rules[0].rule_type).toBe('weekly');
    expect(screen.getByText(tAvail.weeklyRule)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: tAvail.frequency })).toHaveAttribute('aria-haspopup', 'listbox');

    fireEvent.click(screen.getByRole('button', { name: tAvail.addException }));
    expect(useTourWizardStore.getState().formData.availability_exceptions[0].exception_type).toBe('specific');
    expect(screen.getByText(tAvail.specificBadge)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: tAvail.typeLabel })).toHaveAttribute('aria-haspopup', 'listbox');
  });
});
