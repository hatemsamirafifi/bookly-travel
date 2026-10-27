import { render, screen, fireEvent, within, act } from '@testing-library/react';
import { act as reactAct } from 'react';
import { renderToString } from 'react-dom/server';
import { hydrateRoot, type Root } from 'react-dom/client';
import TourBookingController from '../TourBookingController';
import type { AvailabilityInfo, PricingInfo } from '@/lib/api/types';
import { createFullTourDetail, buildOfferedDates } from '../../../../tests/fixtures/tour-detail';
import { formatOfferedDateUtc } from '@/lib/tours/booking-selection';
import { formatCurrency } from '@/lib/utils';
import enMessages from '../../../../messages/en.json';
import esMessages from '../../../../messages/es.json';
import itMessages from '../../../../messages/it.json';

// Spec 020 US2 (T021): one shared page-local selection owner for the desktop
// card and the mobile bar/dialog. Real fixtures, the real pure model and the
// real currency formatter; only system boundaries are mocked (auth context,
// i18n catalogs, next/link, wishlist domain).

const catalogs = { en: enMessages, es: esMessages, it: itMessages };
let mockLocale: 'en' | 'es' | 'it' = 'en';

jest.mock('next-intl', () => ({
  useTranslations: (namespace?: string) => (key: string, values?: Record<string, string | number>) => {
    const root = catalogs[mockLocale] as unknown as Record<string, unknown>;
    const scope = (namespace
      ? namespace.split('.').reduce<unknown>(
          (node, part) => (node as Record<string, unknown> | undefined)?.[part],
          root,
        )
      : root) as unknown as Record<string, unknown>;
    const message =
      (key.split('.').reduce<unknown>(
        (node, part) => (node as Record<string, unknown> | undefined)?.[part],
        scope,
      ) as string | undefined) ?? `${namespace ?? ''}.${key}`;
    return Object.entries(values ?? {}).reduce(
      (text, [name, value]) => text.replace(`{${name}}`, String(value)),
      message,
    );
  },
}));

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, ...rest }: { children: React.ReactNode } & React.AnchorHTMLAttributes<HTMLAnchorElement>) => (
    <a {...rest}>{children}</a>
  ),
}));

const mockUseAuth = jest.fn();
jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => mockUseAuth(),
}));

jest.mock('@/components/wishlist/WishlistButton', () => ({
  __esModule: true,
  default: () => <button type="button">♡</button>,
}));

class MockResizeObserver {
  observe = jest.fn();
  unobserve = jest.fn();
  disconnect = jest.fn();
}
Object.defineProperty(window, 'ResizeObserver', {
  configurable: true,
  writable: true,
  value: MockResizeObserver,
});

const CLOCK = new Date('2026-10-14T00:00:00.000Z');
const TODAY_UTC = '2026-10-14';

function pricing(amount = 4500, currency = 'EUR'): PricingInfo {
  return {
    base_price: { amount, currency, formatted: 'unused-in-selection' },
    tiered_pricing: null,
  };
}

function availability(dates: string[], next: string | null, isUnavailable = false): AvailabilityInfo {
  return { available_dates: dates, next_available_date: next, is_unavailable: isUnavailable };
}

interface ControllerProps {
  slug?: string;
  locale?: string;
  pricing?: PricingInfo;
  availability?: AvailabilityInfo;
  groupMin?: number;
  groupMax?: number;
  todayUtc?: string;
  tourId?: number;
  getTodayUtc?: () => string;
}

function renderController(overrides: ControllerProps = {}) {
  const tour = createFullTourDetail(CLOCK);
  const {
    slug = 'hidden-gems-rome-walking-tour',
    locale = 'en',
    todayUtc = TODAY_UTC,
    getTodayUtc,
    ...rest
  } = overrides;
  // Freeze the clock to the injected day unless the test provides its own.
  const clock = getTodayUtc ?? (() => todayUtc);
  return render(
    <TourBookingController
      slug={slug}
      locale={locale}
      pricing={tour.pricing}
      availability={tour.availability}
      groupMin={tour.group_size.min}
      groupMax={tour.group_size.max}
      tourId={1}
      {...rest}
      todayUtc={todayUtc}
      getTodayUtc={clock}
    />,
  );
}

function cardSelect(): HTMLSelectElement {
  const slot = screen.getByTestId('tour-booking-card-slot');
  return within(slot).getByLabelText('Select a Date') as HTMLSelectElement;
}

beforeEach(() => {
  mockLocale = 'en';
  mockUseAuth.mockReturnValue({ user: { id: 7 }, token: 'token', isLoading: false });
});

afterEach(() => {
  jest.clearAllMocks();
  jest.useRealTimers();
});

describe('TourBookingController shared selection', () => {
  it('initializes the next offered date and minimum count with an exact display-only estimate', () => {
    renderController();
    const tour = createFullTourDetail(CLOCK);
    const slot = screen.getByTestId('tour-booking-card-slot');

    expect(cardSelect().value).toBe(tour.availability.available_dates[0]);
    expect(screen.getByText('1', { selector: 'output' })).toBeInTheDocument();
    expect(within(slot).getByText('Estimated total', { exact: false })).toBeInTheDocument();
    // Two travelers doubles the base exactly; the estimate never enters the
    // checkout URL or any payment amount.
    fireEvent.click(screen.getByRole('button', { name: 'Increase participants' }));
    expect(within(slot).getByText(formatCurrency(9000, 'EUR', 'en'))).toBeInTheDocument();
    const checkout = screen.getByRole('link', { name: 'Book Now' });
    expect(checkout).toHaveAttribute(
      'href',
      `/en/booking?tour=hidden-gems-rome-walking-tour&participants=2&date=${tour.availability.available_dates[0]}`,
    );
    expect(checkout.getAttribute('href')).not.toContain('9000');
  });

  it('offers every future date through the native select, including index 31 (no 30-slice)', () => {
    renderController();
    const dates = buildOfferedDates(CLOCK);

    const options = within(cardSelect()).getAllByRole('option');
    // Placeholder plus all 60 offered dates.
    expect(options).toHaveLength(61);
    expect(options[32]).toHaveValue(dates[31]);
    expect(options[32]).toHaveTextContent(formatOfferedDateUtc(dates[31], 'en'));
  });

  it('keeps card and mobile bar on the same selection across an explicit change', () => {
    renderController();
    const dates = buildOfferedDates(CLOCK);

    fireEvent.change(cardSelect(), { target: { value: dates[9] } });

    expect(cardSelect().value).toBe(dates[9]);
    const bar = screen.getByTestId('mobile-bar-summary');
    expect(bar).toHaveTextContent(formatOfferedDateUtc(dates[9], 'en'));
    const checkout = screen.getByRole('link', { name: 'Book Now' });
    expect(checkout).toHaveAttribute('href', expect.stringContaining(`date=${dates[9]}`));
  });

  it('mounts both surfaces at once so CSS visibility never remounts or resets state', () => {
    renderController();
    const dates = buildOfferedDates(CLOCK);
    fireEvent.change(cardSelect(), { target: { value: dates[5] } });

    expect(screen.getByTestId('tour-booking-card-slot')).toBeInTheDocument();
    expect(screen.getByTestId('mobile-booking-bar')).toBeInTheDocument();
    expect(cardSelect().value).toBe(dates[5]);
    expect(screen.getByTestId('mobile-bar-summary')).toHaveTextContent(
      formatOfferedDateUtc(dates[5], 'en'),
    );
  });

  it('invalidates a now-today selection on UTC rollover with a reselection reason and no substitution', () => {
    const dates = buildOfferedDates(CLOCK);
    let today = TODAY_UTC;
    renderController({ getTodayUtc: () => today });

    expect(cardSelect().value).toBe(dates[0]);

    // Midnight passes: any interaction rechecks the injected UTC day, so the
    // now-today selection is rejected with an explanation, never replaced.
    today = dates[0];
    fireEvent.click(screen.getByRole('button', { name: 'Increase participants' }));

    expect(
      screen.getByText('Available dates changed since the page loaded. Select a date again.'),
    ).toBeInTheDocument();
    expect(cardSelect().value).toBe(dates[0]);
    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
  });

  it('schedules a midnight UTC refresh and releases its timers on unmount', () => {
    jest.useFakeTimers();
    try {
      const dates = buildOfferedDates(CLOCK);
      let today = TODAY_UTC;
      const { unmount } = renderController({ getTodayUtc: () => today });
      expect(cardSelect().value).toBe(dates[0]);

      today = dates[0];
      act(() => {
        jest.advanceTimersByTime(24 * 60 * 60 * 1000 + 1000);
      });

      expect(
        screen.getByText('Available dates changed since the page loaded. Select a date again.'),
      ).toBeInTheDocument();
      unmount();
    } finally {
      jest.useRealTimers();
    }
  });

  it('recovers a missing-next state through an explicit offered selection only', () => {
    const dates = buildOfferedDates(CLOCK);
    renderController({ availability: availability(dates, null) });

    expect(cardSelect().value).toBe('');
    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();

    fireEvent.change(cardSelect(), { target: { value: dates[4] } });

    const checkout = screen.getByRole('link', { name: 'Book Now' });
    expect(checkout).toHaveAttribute(
      'href',
      `/en/booking?tour=hidden-gems-rome-walking-tour&participants=1&date=${dates[4]}`,
    );
  });

  it('invalidates a date removed by fresh props with an explanation and keeps the stale value', () => {
    const dates = buildOfferedDates(CLOCK);
    const { rerender } = renderController();
    fireEvent.change(cardSelect(), { target: { value: dates[9] } });

    const tour = createFullTourDetail(CLOCK);
    const remaining = dates.filter((date) => date !== dates[9]);
    rerender(
      <TourBookingController
        slug="hidden-gems-rome-walking-tour"
        locale="en"
        pricing={tour.pricing}
        availability={availability(remaining, remaining[0])}
        groupMin={tour.group_size.min}
        groupMax={tour.group_size.max}
        todayUtc={TODAY_UTC}
        tourId={1}
      />,
    );

    expect(
      screen.getByText('Available dates changed since the page loaded. Select a date again.'),
    ).toBeInTheDocument();
    expect(cardSelect().value).toBe(dates[9]);
    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
  });

  it('invalidates a count removed by fresh bounds with an explanation and keeps the stale value', () => {
    const { rerender } = renderController({ groupMin: 1, groupMax: 8 });
    const decrease = screen.getByRole('button', { name: 'Decrease participants' });
    const increase = screen.getByRole('button', { name: 'Increase participants' });
    fireEvent.click(increase);
    fireEvent.click(increase);
    expect(screen.getByText('3', { selector: 'output' })).toBeInTheDocument();

    const tour = createFullTourDetail(CLOCK);
    rerender(
      <TourBookingController
        slug="hidden-gems-rome-walking-tour"
        locale="en"
        pricing={tour.pricing}
        availability={tour.availability}
        groupMin={4}
        groupMax={8}
        todayUtc={TODAY_UTC}
        tourId={1}
      />,
    );

    expect(
      screen.getByText('Traveler limits changed. Choose your traveler count again.'),
    ).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
    expect(decrease).toBeInTheDocument();
  });

  it('resets selection when a new tour identity arrives', () => {
    const { rerender } = renderController();
    const dates = buildOfferedDates(CLOCK);
    fireEvent.change(cardSelect(), { target: { value: dates[20] } });
    expect(cardSelect().value).toBe(dates[20]);

    const otherNow = new Date('2026-11-01T00:00:00.000Z');
    const otherDates = buildOfferedDates(otherNow);
    const tour = createFullTourDetail(CLOCK);
    rerender(
      <TourBookingController
        slug="roman-forum-small-group"
        locale="en"
        pricing={tour.pricing}
        availability={availability(otherDates, otherDates[0])}
        groupMin={tour.group_size.min}
        groupMax={tour.group_size.max}
        todayUtc="2026-11-01"
        tourId={2}
      />,
    );

    expect(cardSelect().value).toBe(otherDates[0]);
  });

  it('disables continuation with a precise reason for malformed pricing and never shows a zero estimate', () => {
    renderController({ pricing: pricing(0, 'EUR') });

    expect(
      screen.getByText('The tour price is unavailable. Booking cannot continue.'),
    ).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
    expect(screen.queryByText(formatCurrency(0, 'EUR', 'en'))).not.toBeInTheDocument();
  });

  it('shows the unavailable state and no checkout action when the server flags the tour', () => {
    const dates = buildOfferedDates(CLOCK);
    renderController({ availability: availability(dates, dates[0], true) });

    expect(screen.getByText('Currently Unavailable')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Book Now' })).toBeDisabled();
  });

  it('routes a signed-out traveler to safe login with the full query and offers registration', () => {
    mockUseAuth.mockReturnValue({ user: null, token: null, isLoading: false });
    renderController();
    const dates = buildOfferedDates(CLOCK);
    const bookingPath = `/en/booking?tour=hidden-gems-rome-walking-tour&participants=1&date=${dates[0]}`;

    expect(
      screen.getByText(
        'Sign in to continue booking. Your tour, date, traveler count and language are kept, and nothing is booked until you confirm.',
      ),
    ).toBeInTheDocument();
    const slot = screen.getByTestId('tour-booking-card-slot');
    const login = within(slot).getByRole('link', { name: 'Book Now' });
    expect(login).toHaveAttribute(
      'href',
      `/en/auth/login?returnUrl=${encodeURIComponent(bookingPath)}`,
    );
    const register = within(slot).getByRole('link', { name: /create account/i });
    expect(register).toHaveAttribute(
      'href',
      `/en/auth/register?returnUrl=${encodeURIComponent(bookingPath)}`,
    );
  });

  it('waits for auth restoration before exposing any continuation target', () => {
    mockUseAuth.mockReturnValue({ user: null, token: null, isLoading: true });
    renderController();

    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Book Now' })).toBeDisabled();
  });

  it('uses unique control ids across the card and the mobile dialog', () => {
    renderController();
    fireEvent.click(screen.getByRole('button', { name: 'Edit booking selections' }));

    const ids = Array.from(document.querySelectorAll('[id]')).map((node) => node.id);
    expect(ids.length).toBeGreaterThan(0);
    expect(new Set(ids).size).toBe(ids.length);
  });

  it('returns focus to the actual edit trigger when the mobile dialog closes', () => {
    renderController();
    const trigger = screen.getByRole('button', { name: 'Edit booking selections' });
    trigger.focus();
    fireEvent.click(trigger);

    expect(screen.getByRole('dialog')).toBeInTheDocument();
    fireEvent.keyDown(document, { key: 'Escape' });

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });

  it('keeps the mobile dialog selectors on the same shared state', () => {
    renderController();
    const dates = buildOfferedDates(CLOCK);
    fireEvent.click(screen.getByRole('button', { name: 'Edit booking selections' }));

    const dialog = screen.getByRole('dialog');
    const dialogSelect = within(dialog).getByLabelText('Select a Date') as HTMLSelectElement;
    fireEvent.change(dialogSelect, { target: { value: dates[12] } });

    expect(cardSelect().value).toBe(dates[12]);
    expect(screen.getByTestId('mobile-bar-summary')).toHaveTextContent(
      formatOfferedDateUtc(dates[12], 'en'),
    );
  });

  it('renders Spanish controls and reasons from the catalog without invented copy', () => {
    mockLocale = 'es';
    mockUseAuth.mockReturnValue({ user: null, token: null, isLoading: false });
    render(
      <TourBookingController
        slug="hidden-gems-rome-walking-tour"
        locale="es"
        pricing={pricing()}
        availability={availability(buildOfferedDates(CLOCK), buildOfferedDates(CLOCK)[0])}
        groupMin={1}
        groupMax={8}
        todayUtc={TODAY_UTC}
        tourId={1}
      />,
    );
    const slot = screen.getByTestId('tour-booking-card-slot');

    expect(within(slot).getByText('Total estimado', { exact: false })).toBeInTheDocument();
    expect(
      screen.getByText(
        'Inicia sesión para continuar con la reserva. Tu tour, fecha, número de viajeros e idioma se conservan y no se reserva nada hasta que confirmes.',
      ),
    ).toBeInTheDocument();
  });

  it('rechecks the UTC day on visibility recovery with cleanup', () => {
    let today = TODAY_UTC;
    renderController({ getTodayUtc: () => today });
    const dates = buildOfferedDates(CLOCK);
    expect(cardSelect().value).toBe(dates[0]);

    today = dates[0];
    act(() => {
      document.dispatchEvent(new Event('visibilitychange'));
    });

    expect(
      screen.getByText('Available dates changed since the page loaded. Select a date again.'),
    ).toBeInTheDocument();
  });

  it('prevents a stale card continuation click after the UTC day advances, without timer or visibility events', () => {
    const dates = buildOfferedDates(CLOCK);
    let today = TODAY_UTC;
    renderController({ getTodayUtc: () => today });

    today = dates[0];
    const checkout = within(screen.getByTestId('tour-booking-card-slot')).getByRole('link', {
      name: 'Book Now',
    });
    expect(fireEvent.click(checkout)).toBe(false);

    expect(
      screen.getByText('Available dates changed since the page loaded. Select a date again.'),
    ).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
  });

  it('prevents a stale mobile-bar continuation click through the same single guard', () => {
    const dates = buildOfferedDates(CLOCK);
    let today = TODAY_UTC;
    renderController({ getTodayUtc: () => today });

    today = dates[0];
    const barAction = within(screen.getByTestId('mobile-booking-bar')).getByRole('link', {
      name: 'Continue to booking',
    });
    expect(fireEvent.click(barAction)).toBe(false);

    expect(
      screen.getByText('Available dates changed since the page loaded. Select a date again.'),
    ).toBeInTheDocument();
  });

  it('lets a valid continuation click proceed with native anchor behavior', () => {
    renderController();
    const checkout = within(screen.getByTestId('tour-booking-card-slot')).getByRole('link', {
      name: 'Book Now',
    });
    expect(fireEvent.click(checkout)).toBe(true);
  });

  it('disables date and count controls while auth restores, then enables with provider readiness', () => {
    mockUseAuth.mockReturnValue({ user: null, token: null, isLoading: true });
    const tour = createFullTourDetail(CLOCK);
    const shared = {
      slug: 'hidden-gems-rome-walking-tour',
      locale: 'en',
      pricing: tour.pricing,
      availability: tour.availability,
      groupMin: tour.group_size.min,
      groupMax: tour.group_size.max,
      todayUtc: TODAY_UTC,
      tourId: 1,
      getTodayUtc: () => TODAY_UTC,
    };
    const { rerender } = render(<TourBookingController {...shared} />);
    const dates = buildOfferedDates(CLOCK);

    // While the existing auth status is pending, the visible selectors stay
    // inert: the SSR select cannot revert under the traveler and the stepper
    // cannot act.
    const select = cardSelect();
    expect(select.value).toBe(dates[0]);
    expect(select).toBeDisabled();
    const decrease = screen.getByRole('button', { name: 'Decrease participants' });
    const increase = screen.getByRole('button', { name: 'Increase participants' });
    expect(decrease).toBeDisabled();
    expect(increase).toBeDisabled();
    expect(screen.getByText('1', { selector: 'output' })).toBeInTheDocument();

    // Provider readiness re-enables the same controls naturally; the
    // signed-out continuation appears only once restoration finishes.
    mockUseAuth.mockReturnValue({ user: null, token: null, isLoading: false });
    rerender(<TourBookingController {...shared} />);
    expect(cardSelect()).not.toBeDisabled();
    expect(screen.getByRole('button', { name: 'Increase participants' })).not.toBeDisabled();
    const slot = screen.getByTestId('tour-booking-card-slot');
    expect(within(slot).getByRole('link', { name: 'Book Now' })).toHaveAttribute(
      'href',
      expect.stringContaining('/en/auth/login?returnUrl='),
    );

    fireEvent.click(screen.getByRole('button', { name: 'Edit booking selections' }));
    const dialog = screen.getByRole('dialog');
    const dialogSelect = within(dialog).getByLabelText('Select a Date') as HTMLSelectElement;
    expect(dialogSelect).not.toBeDisabled();
    // The re-enabled dialog stepper acts on the same shared count.
    fireEvent.click(within(dialog).getByRole('button', { name: 'Increase participants' }));
    expect(within(slot).getByText('2', { selector: 'output' })).toBeInTheDocument();
  });

  it('keeps the server snapshot through hydration, then refreshes a pre-midnight day with a stale explanation', async () => {
    const dates = buildOfferedDates(CLOCK);
    const serverDay = TODAY_UTC;
    const clientDay = dates[0];
    const tour = createFullTourDetail(CLOCK);
    const element = (
      <TourBookingController
        slug="hidden-gems-rome-walking-tour"
        locale="en"
        pricing={tour.pricing}
        availability={tour.availability}
        groupMin={tour.group_size.min}
        groupMax={tour.group_size.max}
        todayUtc={serverDay}
        tourId={1}
        getTodayUtc={() => clientDay}
      />
    );

    // The generated document consistently selects the server-day offer.
    const html = renderToString(element);
    expect(html).toContain(`value="${dates[0]}"`);

    const container = document.createElement('div');
    container.innerHTML = html;
    document.body.appendChild(container);
    const hydrationErrors: unknown[] = [];
    let root!: Root;
    try {
      reactAct(() => {
        root = hydrateRoot(container, element, {
          onRecoverableError: (error) => hydrationErrors.push(error),
        });
      });
      // Let the scheduled post-hydration day refresh run.
      await reactAct(async () => {
        await new Promise((resolve) => setTimeout(resolve, 0));
      });

      // Hydration keeps the stale value visible with a localized explanation
      // instead of a silent substitute or a live continuation.
      const select = within(container).getByLabelText('Select a Date') as HTMLSelectElement;
      expect(select.value).toBe(dates[0]);
      expect(
        within(container).getByText(
          'Available dates changed since the page loaded. Select a date again.',
        ),
      ).toBeInTheDocument();
      expect(within(container).queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
      expect(hydrationErrors).toEqual([]);
    } finally {
      reactAct(() => {
        root.unmount();
      });
      container.remove();
    }
  });

  it('measures the visible bar with a real observer lifecycle and cleans page tokens on release', () => {
    const observe = jest.fn();
    const disconnect = jest.fn();
    class LifecycleObserver {
      observe = observe;
      unobserve = jest.fn();
      disconnect = disconnect;
    }
    Object.defineProperty(window, 'ResizeObserver', {
      configurable: true,
      writable: true,
      value: LifecycleObserver,
    });
    try {
      const tour = createFullTourDetail(CLOCK);
      const { rerender, unmount } = renderController();
      expect(observe).toHaveBeenCalledTimes(1);
      expect(
        document.documentElement.style.getPropertyValue('--tour-booking-bar-height'),
      ).not.toBe('');

      rerender(
        <TourBookingController
          slug="hidden-gems-rome-walking-tour"
          locale="en"
          pricing={tour.pricing}
          availability={{
            available_dates: [],
            next_available_date: null,
            is_unavailable: true,
          }}
          groupMin={tour.group_size.min}
          groupMax={tour.group_size.max}
          todayUtc={TODAY_UTC}
          tourId={1}
        />,
      );
      expect(disconnect).toHaveBeenCalled();
      expect(
        document.documentElement.style.getPropertyValue('--tour-booking-bar-height'),
      ).toBe('');
    expect(
      document.documentElement.style.getPropertyValue('scroll-padding-bottom'),
    ).toBe('');
    unmount();
    } finally {
      document.documentElement.style.removeProperty('--tour-booking-bar-height');
      document.documentElement.style.removeProperty('scroll-padding-bottom');
    }
  });

  it('blocks a stale registration continuation click with the same day recheck as Book Now', () => {
    mockUseAuth.mockReturnValue({ user: null, token: null, isLoading: false });
    const dates = buildOfferedDates(CLOCK);
    let today = TODAY_UTC;
    renderController({ getTodayUtc: () => today });
    const slot = screen.getByTestId('tour-booking-card-slot');

    // A valid selection keeps native anchor behavior on the register anchor,
    // including modifier clicks.
    const freshRegister = within(slot).getByRole('link', { name: /create account/i });
    expect(fireEvent.click(freshRegister)).toBe(true);
    expect(fireEvent.click(freshRegister, { ctrlKey: true })).toBe(true);

    // At UTC midnight before any scheduled callback, the register anchor
    // rechecks the day exactly like Book Now: no silent expired navigation,
    // no substituted date.
    today = dates[0];
    const staleRegister = within(slot).getByRole('link', { name: /create account/i });
    expect(fireEvent.click(staleRegister)).toBe(false);

    expect(
      screen.getByText('Available dates changed since the page loaded. Select a date again.'),
    ).toBeInTheDocument();
    expect(cardSelect().value).toBe(dates[0]);
    expect(within(slot).queryByRole('link', { name: /create account/i })).not.toBeInTheDocument();
  });

  it('marks the supplied cancellation policy with its content language without changing control language', () => {
    mockLocale = 'es';
    mockUseAuth.mockReturnValue({ user: null, token: null, isLoading: false });
    const tour = createFullTourDetail(CLOCK);
    render(
      <TourBookingController
        slug="hidden-gems-rome-walking-tour"
        locale="es"
        pricing={tour.pricing}
        availability={tour.availability}
        groupMin={tour.group_size.min}
        groupMax={tour.group_size.max}
        todayUtc={TODAY_UTC}
        tourId={1}
        getTodayUtc={() => TODAY_UTC}
        cancellationPolicy={'Free cancellation up to 24 hours\nbefore the tour starts.'}
        cancellationPolicyLocale="en"
      />,
    );
    const slot = screen.getByTestId('tour-booking-card-slot');

    // The actual policy text carries its own language and keeps line breaks;
    // the surrounding controls stay in the page language.
    const policy = within(slot).getByText(/Free cancellation up to 24 hours/);
    expect(policy).toHaveAttribute('lang', 'en');
    expect(policy.className).toContain('whitespace-pre-line');
    expect(within(slot).getByText('Total estimado', { exact: false })).toBeInTheDocument();
  });

  it('keeps an open shared dialog inert while auth is pending, then re-enables with readiness', () => {
    const tour = createFullTourDetail(CLOCK);
    const shared = {
      slug: 'hidden-gems-rome-walking-tour',
      locale: 'en',
      pricing: tour.pricing,
      availability: tour.availability,
      groupMin: tour.group_size.min,
      groupMax: tour.group_size.max,
      todayUtc: TODAY_UTC,
      tourId: 1,
      getTodayUtc: () => TODAY_UTC,
    };
    // Ready auth opens the shared dialog first (this test starts signed-in).
    const { rerender } = render(<TourBookingController {...shared} />);
    const dates = buildOfferedDates(CLOCK);
    fireEvent.click(screen.getByRole('button', { name: 'Edit booking selections' }));
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(cardSelect().value).toBe(dates[0]);

    // Provider pending on rerender: the visible dialog selectors disable
    // without disturbing the shared selection.
    mockUseAuth.mockReturnValue({ user: null, token: null, isLoading: true });
    rerender(<TourBookingController {...shared} />);
    const pendingDialog = screen.getByRole('dialog');
    const dialogSelect = within(pendingDialog).getByLabelText('Select a Date') as HTMLSelectElement;
    expect(dialogSelect.value).toBe(dates[0]);
    expect(dialogSelect).toBeDisabled();
    expect(
      within(pendingDialog).getByRole('button', { name: 'Decrease participants' }),
    ).toBeDisabled();
    expect(
      within(pendingDialog).getByRole('button', { name: 'Increase participants' }),
    ).toBeDisabled();
    expect(cardSelect().value).toBe(dates[0]);

    // Readiness re-enables the same dialog controls naturally.
    mockUseAuth.mockReturnValue({ user: { id: 7 }, token: 'token', isLoading: false });
    rerender(<TourBookingController {...shared} />);
    const readyDialog = screen.getByRole('dialog');
    expect(within(readyDialog).getByLabelText('Select a Date')).not.toBeDisabled();
    expect(
      within(readyDialog).getByRole('button', { name: 'Increase participants' }),
    ).not.toBeDisabled();
  });

  it('keeps the bookable card desktop-only while exposing the unavailable card on mobile', () => {
    // CSS visibility contract bounded in jsdom (which cannot media-evaluate):
    // the slot hides below lg only while the tour is bookable. An unavailable
    // tour must keep its existing copy reachable on mobile; the mobile fixed
    // bar stays absent by design (MobileBookingBar returns null).
    const { unmount } = renderController();
    const bookableSlot = screen.getByTestId('tour-booking-card-slot');
    expect(bookableSlot).toHaveClass('hidden');
    expect(bookableSlot).toHaveClass('lg:block');
    unmount();

    renderController({ availability: availability([], null, true) });
    const unavailableSlot = screen.getByTestId('tour-booking-card-slot');
    expect(unavailableSlot).not.toHaveClass('hidden');
    expect(unavailableSlot).toHaveClass('lg:block');
  });

  it('shows the localized unavailable copy with a disabled continuation and no checkout target', () => {
    renderController({ availability: availability([], null, true) });
    const slot = screen.getByTestId('tour-booking-card-slot');

    expect(within(slot).getByText('Currently Unavailable')).toBeInTheDocument();
    expect(
      within(slot).getByText('No upcoming dates are available for this tour. Check back soon.'),
    ).toBeInTheDocument();
    const action = within(slot).getByRole('button', { name: 'Book Now' });
    expect(action).toBeDisabled();
    expect(action).toHaveAttribute('aria-disabled', 'true');
    expect(within(slot).queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
    expect(screen.queryByTestId('mobile-booking-bar')).toBeNull();
  });

  it('moves between available and unavailable without stale checkout hrefs', () => {
    const tour = createFullTourDetail(CLOCK);
    const shared = {
      slug: 'hidden-gems-rome-walking-tour',
      locale: 'en',
      pricing: tour.pricing,
      groupMin: tour.group_size.min,
      groupMax: tour.group_size.max,
      todayUtc: TODAY_UTC,
      tourId: 1,
      getTodayUtc: () => TODAY_UTC,
    };
    const dates = buildOfferedDates(CLOCK);
    const href = `/en/booking?tour=hidden-gems-rome-walking-tour&participants=1&date=${dates[0]}`;
    const { rerender } = render(
      <TourBookingController {...shared} availability={availability(dates, dates[0], false)} />,
    );
    expect(screen.getByRole('link', { name: 'Book Now' })).toHaveAttribute('href', href);

    rerender(
      <TourBookingController {...shared} availability={availability(dates, dates[0], true)} />,
    );
    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Book Now' })).toBeDisabled();
    expect(screen.getByText('Currently Unavailable')).toBeInTheDocument();

    rerender(
      <TourBookingController {...shared} availability={availability(dates, dates[0], false)} />,
    );
    expect(screen.getByRole('link', { name: 'Book Now' })).toHaveAttribute('href', href);
  });
});
