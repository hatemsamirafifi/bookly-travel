import type { ReactNode } from 'react';
import { Suspense, act } from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { NextIntlClientProvider } from 'next-intl';
import AvailabilityPage from '../page';
import { ValidationError } from '@/lib/api/client';
import {
  getPartnerAvailability,
  getPartnerDepartures,
  createPartnerDeparture,
  generatePartnerDepartures,
  updatePartnerDeparture,
  cancelPartnerDeparture,
} from '@/lib/api/partner';
import en from '../../../../../../../../../messages/en.json';
import es from '../../../../../../../../../messages/es.json';
import itMessages from '../../../../../../../../../messages/it.json';

jest.mock('next-intl', () => jest.requireActual('@/test-utils/catalogIntl'));

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href }: { children: ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  ),
}));

jest.mock('@/lib/api/partner', () => {
  const actual = jest.requireActual('@/lib/api/partner');
  return {
    ...actual,
    getPartnerAvailability: jest.fn(),
    getPartnerDepartures: jest.fn(),
    createPartnerDeparture: jest.fn(),
    generatePartnerDepartures: jest.fn(),
    updatePartnerDeparture: jest.fn(),
    cancelPartnerDeparture: jest.fn(),
    createPartnerAvailabilityRule: jest.fn(),
    deletePartnerAvailabilityRule: jest.fn(),
    createPartnerAvailabilityException: jest.fn(),
    deletePartnerAvailabilityException: jest.fn(),
  };
});

const mockedGetAvailability = getPartnerAvailability as jest.Mock;
const mockedGetDepartures = getPartnerDepartures as jest.Mock;
const mockedCreateDeparture = createPartnerDeparture as jest.Mock;
const mockedGenerateDepartures = generatePartnerDepartures as jest.Mock;
const mockedUpdateDeparture = updatePartnerDeparture as jest.Mock;
const mockedCancelDeparture = cancelPartnerDeparture as jest.Mock;

const catalogs = { en, es, it: itMessages };
type Locale = keyof typeof catalogs;

const baseOption = {
  id: 11,
  title: 'Morning Colosseum Group',
  start_time: '09:00:00',
  group_size_max: 12,
  effective_capacity: 12,
};

const baseDeparture = {
  id: 101,
  tour_id: 42,
  tour_option_id: 11,
  option_title: 'Morning Colosseum Group',
  starts_at: '2027-06-15T07:00:00Z',
  timezone: 'Europe/Rome',
  local_date: '2027-06-15',
  local_time: '09:00:00',
  capacity: 10,
  reserved_seats: 4,
  available_spots: 6,
  booking_cutoff_hours: 2,
  status: 'active' as const,
  is_bookable: true,
};

async function renderAvailabilityPage(locale: Locale = 'en') {
  const params = Promise.resolve({ id: '42', locale });
  await act(async () => {
    render(
      <NextIntlClientProvider locale={locale} messages={catalogs[locale]} timeZone="UTC">
        <Suspense fallback={<div>Loading...</div>}>
          <AvailabilityPage params={params} />
        </Suspense>
      </NextIntlClientProvider>
    );
  });
}

describe('AvailabilityPage (EGM-005 D3 & D4)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockedGetAvailability.mockResolvedValue({
      data: {
        rules: [
          {
            id: 7,
            rule_type: 'recurring',
            days_of_week: [1, 3, 5],
            start_time: '09:00:00',
            start_date: '2027-05-01',
            end_date: null,
            capacity: 10,
          },
        ],
        exceptions: [],
      },
    });
    mockedGetDepartures.mockResolvedValue({
      data: [baseDeparture],
      meta: {
        total: 1,
        max_horizon_days: 180,
        options: [baseOption],
      },
    });
  });

  it.each(['en', 'es', 'it'] as const)(
    'renders localized schedule and departure controls for %s without crashing',
    async (locale) => {
      await renderAvailabilityPage(locale);

      const tAvail = catalogs[locale].partner.tours.availability;
      const tDep = catalogs[locale].partner.tours.departures;

      await waitFor(() => {
        expect(screen.getByText(tAvail.pageTitle)).toBeInTheDocument();
      });
      expect(screen.getByText(tDep.sectionTitle)).toBeInTheDocument();
      expect(screen.getByText('2027-06-15 · 09:00')).toBeInTheDocument();
      expect(screen.getByTestId('departure-row-101')).toBeInTheDocument();
    }
  );

  it('surfaces inline D4 Rule 10 422 alert when reducing capacity below reserved seats', async () => {
    mockedUpdateDeparture.mockRejectedValue(
      new ValidationError('Validation failed', {
        capacity: ['Cannot reduce departure capacity below currently reserved seats (4).'],
      })
    );

    await renderAvailabilityPage('en');

    const row = await screen.findByTestId('departure-row-101');
    fireEvent.click(screen.getByRole('button', { name: en.partner.tours.departures.editButton }));

    const capInput = row.querySelector('#edit-cap-101') as HTMLInputElement;
    fireEvent.change(capInput, { target: { value: '2' } });
    fireEvent.click(screen.getByRole('button', { name: en.partner.tours.departures.saveEditButton }));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent(
        'Cannot reduce departure capacity below currently reserved seats (4).'
      );
    });
    expect(mockedUpdateDeparture).toHaveBeenCalledWith('42', 101, {
      capacity: 2,
      booking_cutoff_hours: 2,
    });
  });

  it('cancels a booked departure only after explicit accessible confirmation without window.confirm', async () => {
    const confirmSpy = jest.spyOn(window, 'confirm').mockImplementation(() => true);
    mockedCancelDeparture.mockResolvedValue({
      data: { ...baseDeparture, status: 'cancelled' },
    });

    await renderAvailabilityPage('en');

    await screen.findByTestId('departure-row-101');
    fireEvent.click(screen.getByRole('button', { name: en.partner.tours.departures.cancelButton }));

    const confirmBtn = screen.getByRole('button', {
      name: en.partner.tours.departures.confirmCancelButton,
    });
    expect(confirmBtn).toBeDisabled();

    const checkbox = screen.getByRole('checkbox');
    fireEvent.click(checkbox);
    expect(confirmBtn).toBeEnabled();

    fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(mockedCancelDeparture).toHaveBeenCalledWith('42', 101, {
        confirm_booked_cancellation: true,
      });
    });
    expect(confirmSpy).not.toHaveBeenCalled();
    confirmSpy.mockRestore();
  });

  it('submits create departure and bounded generate departure forms', async () => {
    mockedGenerateDepartures.mockResolvedValue({
      data: [baseDeparture],
      meta: { generated_count: 1 },
    });
    mockedCreateDeparture.mockResolvedValue({
      data: { ...baseDeparture, id: 102, local_date: '2027-07-01' },
    });

    await renderAvailabilityPage('en');
    await screen.findByTestId('departure-row-101');

    fireEvent.click(screen.getByTestId('generate-departures-submit'));
    await waitFor(() => {
      expect(mockedGenerateDepartures).toHaveBeenCalledWith(
        '42',
        expect.objectContaining({
          timezone: 'Europe/Rome',
        })
      );
    });

    fireEvent.click(screen.getByTestId('create-departure-submit'));
    await waitFor(() => {
      expect(mockedCreateDeparture).toHaveBeenCalledWith(
        '42',
        expect.objectContaining({
          tour_option_id: 11,
          local_time: '09:00',
          timezone: 'Europe/Rome',
          capacity: 10,
        })
      );
    });
  });
});
