import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import BookingForm, { buildCheckoutAuthRecoveryUrl } from '../BookingForm';
import type { TourDetail } from '@/lib/api/types';
import { ApiError } from '@/lib/api/client';
import {
  buildOfferedDates,
  createFullTourDetail,
} from '../../../../tests/fixtures/tour-detail';
import enMessages from '../../../../messages/en.json';
import type { CreateBookingResult } from '@/lib/api/bookings';

// Spec 020 US2: complete typed fixture (no partial casts) with an explicit
// frozen UTC clock. Real catalogs resolve both namespaces; only system
// boundaries (network API fns, auth context, router) are mocked.

jest.mock('@/lib/api/bookings', () => ({
  createBooking: jest.fn(),
  confirmDeterministicPayment: jest.fn(),
}));

jest.mock('@/lib/api/tours', () => ({
  getTourDetail: jest.fn(),
}));

jest.mock('@/lib/api/my-bookings', () => ({
  cancelBooking: jest.fn(),
}));

const mockPush = jest.fn();
let mockQuery = '';
// Mirror the real SDK: the params snapshot identity is stable while the query
// string is unchanged, so effect dependencies do not refire every render.
let mockCachedQuery: string | null = null;
let mockCachedParams: URLSearchParams | null = null;
jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: mockPush }),
  useSearchParams: () => {
    if (mockCachedParams === null || mockCachedQuery !== mockQuery) {
      mockCachedQuery = mockQuery;
      mockCachedParams = new URLSearchParams(mockQuery);
    }
    return mockCachedParams;
  },
}));

const mockUseAuth = jest.fn();
jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => mockUseAuth(),
}));

type MockTranslator = (key: string, values?: Record<string, string | number>) => string;
// Mirror the real SDK: one stable translator per namespace, so translation
// identities do not refire effects every render.
const mockTranslatorCache = new Map<string, MockTranslator>();
jest.mock('next-intl', () => ({
  useTranslations: (namespace?: string) => {
    const cacheKey = namespace ?? '';
    let translate = mockTranslatorCache.get(cacheKey);
    if (!translate) {
      translate = (key: string, values?: Record<string, string | number>) => {
        const root = enMessages as unknown as Record<string, unknown>;
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
          ) as string | undefined) ?? key;
        return Object.entries(values ?? {}).reduce(
          (text, [name, value]) => text.replace(`{${name}}`, String(value)),
          message,
        );
      };
      mockTranslatorCache.set(cacheKey, translate);
    }
    return translate;
  },
}));

// The composed presentational children (ParticipantSelector,
// DateConfirmation, PriceBreakdown) stay REAL so visible-price regressions
// surface; only system boundaries (network API fns, auth context, router,
// Stripe SDK) are mocked. The deterministic payment form stays REAL: its 401
// preservation behavior is part of this flow.
jest.mock('../PriceChangeModal', () => function PriceChangeModal() {
  return null;
});
jest.mock('../StripePaymentForm', () => function StripePaymentForm() {
  return <div data-testid="stripe-form" />;
});
jest.mock('@/lib/stripe/stripe-client', () => ({ getStripe: () => Promise.resolve(null) }));

import { createBooking, confirmDeterministicPayment } from '@/lib/api/bookings';
import { getTourDetail } from '@/lib/api/tours';
import { formatCurrency } from '@/lib/utils';

const CLOCK = new Date('2026-10-14T00:00:00.000Z');
const TODAY_UTC = '2026-10-14';
const FUTURE_DATE = buildOfferedDates(CLOCK)[0];

function bookingTour(overrides: Partial<TourDetail> = {}): { data: TourDetail } {
  return {
    data: createFullTourDetail(CLOCK, {
      slug: 'rome-food-walk',
      group_size: { min: 1, max: 8 },
      pricing: {
        base_price: { amount: 8900, currency: 'EUR', formatted: '€89.00' },
        tiered_pricing: null,
      },
      ...overrides,
    }),
  };
}

const successResult: CreateBookingResult = {
  data: {
    reference: 'BKO-TEST',
    tour: { slug: 'rome-food-walk', title: 'Rome Food Walk', location: 'Rome', cover_image_url: '' },
    tour_date: FUTURE_DATE,
    participant_count: 2,
    total_price: { amount: 17800, currency: 'EUR', formatted: '€178.00' },
    status: 'pending_payment',
    cancellation_policy: '',
    cancellation_window_hours: 24,
    can_cancel: true,
    created_at: '2026-01-01T00:00:00Z',
  },
  price_changed: false,
  payment: undefined,
};

const deterministicResult: CreateBookingResult = {
  ...successResult,
  payment: {
    client_secret: 'cs_test_deterministic',
    stripe_publishable_key: null,
    gateway: 'deterministic',
  },
};

function renderForm() {
  return render(<BookingForm locale="en" todayUtc={TODAY_UTC} />);
}

describe('BookingForm', () => {
  beforeEach(() => {
    mockQuery = `tour=rome-food-walk&date=${FUTURE_DATE}&participants=2`;
    mockCachedQuery = null;
    mockCachedParams = null;
    mockUseAuth.mockReturnValue({ user: { id: 7 }, token: 'token', isLoading: false });
    jest.mocked(getTourDetail).mockResolvedValue(bookingTour());
    jest.mocked(createBooking).mockResolvedValue(successResult);
    jest.mocked(confirmDeterministicPayment).mockResolvedValue({
      data: { reference: 'BKO-TEST', status: 'confirmed' },
    });
  });

  afterEach(() => {
    jest.clearAllMocks();
    localStorage.clear();
  });

  // F2: a synchronous submit guard prevents a double-click from issuing two
  // createBooking requests (which would otherwise race and create duplicates).
  it('submits only once when the confirm button is double-clicked', async () => {
    renderForm();

    const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
    expect(button).not.toBeDisabled();

    fireEvent.click(button);
    fireEvent.click(button);

    await waitFor(() => {
      expect(createBooking).toHaveBeenCalledTimes(1);
    });
  });

  // Spec 020 US2: the recovery target preserves the full selected
  // tour/date/count URL through safe login with a session-expired notice.
  it('builds an expired-session recovery URL that keeps the full booking query', () => {
    expect(buildCheckoutAuthRecoveryUrl('en', 'rome-food-walk', 2, FUTURE_DATE)).toBe(
      '/en/auth/login?sessionExpired=1&returnUrl=' +
        encodeURIComponent(`/en/booking?tour=rome-food-walk&participants=2&date=${FUTURE_DATE}`),
    );
  });

  // Spec 020 US2: an expired session (401) clears the stale stored token
  // without calling logout (which would push home and drop the selection),
  // hard-navigates to safe login, and never retries the POST.
  it('recovers from an expired-session 401 with the full selection intact and no automatic retry', async () => {
    localStorage.setItem('auth_token', 'stale-expired-token');
    jest.mocked(createBooking).mockRejectedValueOnce(new ApiError('Unauthorized', 401));
    renderForm();

    const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
    fireEvent.click(button);

    await waitFor(() => {
      expect(createBooking).toHaveBeenCalledTimes(1);
    });
    expect(localStorage.getItem('auth_token')).toBeNull();
    expect(mockPush).not.toHaveBeenCalled();
    // No automatic resubmission after the recovery redirect.
    await waitFor(() => {
      expect(createBooking).toHaveBeenCalledTimes(1);
    });
  });

  // Spec 020 US2 rework: only a missing parameter defaults. An explicitly
  // supplied empty value is invalid, never silently replaced by the minimum.
  it.each(['0', '2.5', 'abc', ''])(
    'retains a supplied invalid participant value %s without coercing or submitting',
    async (supplied) => {
      mockQuery = `tour=rome-food-walk&date=${FUTURE_DATE}&participants=${supplied}`;
      renderForm();

      const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
      expect(button).toBeDisabled();
      expect(screen.getByText(/Invalid booking details/)).toBeInTheDocument();
      // Both the real date confirmation and the invalid-count alert expose
      // the same tour reselection route.
      const changeLinks = screen.getAllByRole('link', { name: /change date/i });
      expect(changeLinks).toHaveLength(2);
      for (const link of changeLinks) {
        expect(link).toHaveAttribute('href', '/en/tours/rome-food-walk');
      }
      expect(createBooking).not.toHaveBeenCalled();
    },
  );

  it('defaults an absent participant value to the tour minimum', async () => {
    mockQuery = `tour=rome-food-walk&date=${FUTURE_DATE}`;
    renderForm();

    const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
    expect(button).not.toBeDisabled();
    expect(screen.queryByText(/Invalid booking details/)).not.toBeInTheDocument();
  });

  // Spec 020 US2: a supplied malformed date is retained with an explanation
  // and a tour reselection route instead of rendering a broken confirmation.
  it('retains a supplied malformed date with a reselection route and blocks submit', async () => {
    mockQuery = 'tour=rome-food-walk&date=not-a-date&participants=2';
    renderForm();

    const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
    expect(button).toBeDisabled();
    expect(screen.getByText(/Invalid booking details/)).toBeInTheDocument();
    // The malformed date renders no broken confirmation, only the alert with
    // the tour reselection route.
    expect(screen.queryByText('Selected Date')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: /change date/i })).toHaveAttribute(
      'href',
      '/en/tours/rome-food-walk',
    );
    expect(createBooking).not.toHaveBeenCalled();
  });

  // Spec 020 US2 rework: the accepted selection view guards the checkout
  // against stale inventory. Past, unlisted and unavailable dates disable the
  // submit with a precise explanation; 409/422/429 stay server-authoritative.
  it.each([
    ['2026-10-01', 'past'],
    ['2026-12-25', 'unlisted'],
  ])('blocks submit for a %s offered date with an explanation and reselection route', async (date) => {
    mockQuery = `tour=rome-food-walk&date=${date}&participants=2`;
    renderForm();

    const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
    expect(button).toBeDisabled();
    expect(screen.getByText('Select an offered date to continue.')).toBeInTheDocument();
    // Both the real date confirmation and the invalid-date alert expose the
    // same tour reselection route.
    const changeLinks = screen.getAllByRole('link', { name: /change date/i });
    expect(changeLinks).toHaveLength(2);
    for (const link of changeLinks) {
      expect(link).toHaveAttribute('href', '/en/tours/rome-food-walk');
    }
    expect(createBooking).not.toHaveBeenCalled();
  });

  it('blocks submit with an unavailable explanation when the server flags the tour', async () => {
    jest.mocked(getTourDetail).mockResolvedValue(
      bookingTour({
        availability: { available_dates: [], next_available_date: null, is_unavailable: true },
      }),
    );
    renderForm();

    const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
    expect(button).toBeDisabled();
    expect(screen.getByText('Currently Unavailable')).toBeInTheDocument();
    expect(createBooking).not.toHaveBeenCalled();
  });

  it('blocks submit with a pricing explanation for an invalid tour price', async () => {
    jest.mocked(getTourDetail).mockResolvedValue(
      bookingTour({
        pricing: {
          base_price: { amount: 0, currency: 'EUR', formatted: '€0.00' },
          tiered_pricing: null,
        },
      }),
    );
    renderForm();

    const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
    expect(button).toBeDisabled();
    expect(
      screen.getByText('The tour price is unavailable. Booking cannot continue.'),
    ).toBeInTheDocument();
    // Invalid pricing renders no bogus zero quote (per-person label or
    // breakdown) — the reason explains instead.
    expect(screen.queryByText('€0.00 / person')).not.toBeInTheDocument();
    expect(screen.queryByText('Price Breakdown')).not.toBeInTheDocument();
    expect(createBooking).not.toHaveBeenCalled();
  });

  // Spec 020 US2 money boundary: the visible quote comes from the accepted
  // exact-cents view, never the raw server string or a float multiply. At
  // MAX_SAFE_INTEGER cents the exact total ends in .91 (float math would
  // render .90), and a deliberately conflicting supplied formatted string
  // must appear nowhere.
  it('quotes exact cents at the safe-integer boundary instead of the supplied formatted string', async () => {
    const exact = formatCurrency(Number.MAX_SAFE_INTEGER, 'EUR', 'en');
    expect(exact).toContain('.91');
    mockQuery = `tour=rome-food-walk&date=${FUTURE_DATE}&participants=1`;
    jest.mocked(getTourDetail).mockResolvedValueOnce(
      bookingTour({
        pricing: {
          base_price: { amount: Number.MAX_SAFE_INTEGER, currency: 'EUR', formatted: '€0.01' },
          tiered_pricing: null,
        },
      }),
    );
    renderForm();

    const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
    expect(button).not.toBeDisabled();
    // Per-person label plus the breakdown totals all carry the exact value;
    // the conflicting supplied string appears nowhere.
    expect(screen.getByText(`${exact} / person`)).toBeInTheDocument();
    expect(screen.getAllByText(exact).length).toBeGreaterThanOrEqual(2);
    expect(screen.queryByText('€0.01 / person')).not.toBeInTheDocument();
    expect(createBooking).not.toHaveBeenCalled();
  });

  // F2: a failed submit keeps the same selection, so retrying it reuses the
  // same idempotency key and payload at the API boundary (no duplicate
  // booking), instead of minting a fresh key per attempt.
  it('reuses the same idempotency key and payload when a failed submit is retried', async () => {
    jest.mocked(createBooking).mockRejectedValueOnce(new ApiError('Server error', 500));
    renderForm();

    const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
    fireEvent.click(button);
    await waitFor(() => {
      expect(createBooking).toHaveBeenCalledTimes(1);
    });
    expect(screen.getByText('Something went wrong. Please try again.')).toBeInTheDocument();

    fireEvent.click(await screen.findByRole('button', { name: 'Confirm & Pay' }));
    await waitFor(() => {
      expect(createBooking).toHaveBeenCalledTimes(2);
    });
    const [firstPayload, firstKey] = jest.mocked(createBooking).mock.calls[0];
    const [secondPayload, secondKey] = jest.mocked(createBooking).mock.calls[1];
    expect(secondKey).toBe(firstKey);
    expect(secondPayload).toEqual(firstPayload);
    await waitFor(() => {
      expect(mockPush).toHaveBeenCalledWith('/en/booking/confirmation?ref=BKO-TEST');
    });
  });

  // Spec 020 US2: a known signed-out checkout uses the safe full-query auth
  // continuation instead of posting into a 401.
  it('offers safe login continuation with the full query instead of submitting when signed out', async () => {
    mockUseAuth.mockReturnValue({ user: null, token: null, isLoading: false });
    renderForm();

    const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
    expect(button).toBeDisabled();
    expect(
      screen.getByText(/Sign in to continue booking/),
    ).toBeInTheDocument();
    const bookingPath = `/en/booking?tour=rome-food-walk&participants=2&date=${FUTURE_DATE}`;
    expect(screen.getByRole('link', { name: 'Sign In' })).toHaveAttribute(
      'href',
      `/en/auth/login?returnUrl=${encodeURIComponent(bookingPath)}`,
    );
    expect(screen.getByRole('link', { name: 'Create Account' })).toHaveAttribute(
      'href',
      `/en/auth/register?returnUrl=${encodeURIComponent(bookingPath)}`,
    );
    expect(createBooking).not.toHaveBeenCalled();
  });

  // Spec 020 US2: checkout waits for auth restoration instead of submitting
  // against a stale context that would bounce a router-only redirect.
  it('disables submit while auth restoration is still loading', async () => {
    mockUseAuth.mockReturnValue({ user: null, token: null, isLoading: true });
    renderForm();

    const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
    expect(button).toBeDisabled();
    expect(createBooking).not.toHaveBeenCalled();
  });

  // Spec 020 US2 rework item 8 (payment-401 boundary, mocked confirm API):
  // after the create step, a 401 on the deterministic confirm surfaces a
  // failure while the SAME booking reference and selection are kept; a retry
  // after restored auth confirms that reference with no additional create.
  it('keeps the same booking reference through a failed 401 payment confirm and retries without a new create', async () => {
    jest.mocked(createBooking).mockResolvedValueOnce(deterministicResult);
    jest.mocked(confirmDeterministicPayment).mockRejectedValueOnce(new ApiError('Unauthorized', 401));
    renderForm();

    fireEvent.click(await screen.findByRole('button', { name: 'Confirm & Pay' }));

    const payButton = await screen.findByRole('button', { name: 'Complete test payment' });
    expect(payButton).not.toBeDisabled();
    fireEvent.click(payButton);

    await waitFor(() => {
      expect(screen.getByText('The local test payment could not be completed.')).toBeInTheDocument();
    });
    // The failed confirm created nothing new and kept the pending selection.
    expect(createBooking).toHaveBeenCalledTimes(1);
    expect(confirmDeterministicPayment).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('button', { name: 'Complete test payment' })).not.toBeDisabled();
    expect(screen.getByText('Hidden Gems of Rome Walking Tour')).toBeInTheDocument();

    // Restored auth retries the SAME confirm; still no additional create POST.
    jest.mocked(confirmDeterministicPayment).mockResolvedValueOnce({
      data: { reference: 'BKO-TEST', status: 'confirmed' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Complete test payment' }));

    await waitFor(() => {
      expect(mockPush).toHaveBeenCalledWith('/en/booking/confirmation?ref=BKO-TEST');
    });
    expect(createBooking).toHaveBeenCalledTimes(1);
    expect(confirmDeterministicPayment).toHaveBeenCalledTimes(2);
  });

  // Spec 020 US2 checkout freshness: a same-path query transition to B must
  // withhold A's readable quote until B's own fetch resolves — A's details
  // can never submit B, and nothing posts without an explicit confirm.
  it('withholds the previous quote while a new query selection is loading, then submits only the resolved selection', async () => {
    const { rerender } = renderForm();

    // A is fully loaded and submittable on its own facts.
    expect(await screen.findByText('Hidden Gems of Rome Walking Tour')).toBeInTheDocument();
    expect(screen.getAllByText('€178.00').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByRole('button', { name: 'Confirm & Pay' })).not.toBeDisabled();

    // Transition to B while B's response is still pending.
    let resolveB!: (value: { data: TourDetail }) => void;
    jest.mocked(getTourDetail).mockImplementationOnce(
      () => new Promise<{ data: TourDetail }>((resolve) => { resolveB = resolve; }),
    );
    mockQuery = `tour=b-course-walk&date=${FUTURE_DATE}&participants=1`;
    rerender(<BookingForm locale="en" todayUtc={TODAY_UTC} />);

    // No stale readable selection: skeleton only, A's quote gone, no submit.
    await waitFor(() => {
      expect(screen.queryByText('Hidden Gems of Rome Walking Tour')).not.toBeInTheDocument();
    });
    expect(screen.queryByText('€178.00')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Confirm & Pay' })).not.toBeInTheDocument();
    expect(createBooking).not.toHaveBeenCalled();

    // B resolves: B's own facts render and only an explicit confirm posts B.
    resolveB(bookingTour({
      slug: 'b-course-walk',
      title: 'B Course Walk',
      group_size: { min: 1, max: 4 },
      pricing: {
        base_price: { amount: 5000, currency: 'EUR', formatted: '€0.01' },
        tiered_pricing: null,
      },
    }));
    expect(await screen.findByText('B Course Walk')).toBeInTheDocument();
    expect(screen.getByText('€50.00 / person')).toBeInTheDocument();
    expect(screen.queryByText('€0.01 / person')).not.toBeInTheDocument();
    const button = screen.getByRole('button', { name: 'Confirm & Pay' });
    expect(button).not.toBeDisabled();
    expect(createBooking).not.toHaveBeenCalled();

    fireEvent.click(button);
    await waitFor(() => {
      expect(createBooking).toHaveBeenCalledTimes(1);
    });
    expect(jest.mocked(createBooking).mock.calls[0][0]).toMatchObject({
      tour_slug: 'b-course-walk',
      tour_date: FUTURE_DATE,
      participant_count: 1,
    });
    expect(getTourDetail).toHaveBeenCalledTimes(2);
  });

  it('withholds an absent-count default when the query changes to an explicitly empty count', async () => {
    mockQuery = `tour=rome-food-walk&date=${FUTURE_DATE}`;
    const { rerender } = renderForm();
    expect(await screen.findByRole('button', { name: 'Confirm & Pay' })).toBeEnabled();

    let resolveCurrent!: (response: { data: TourDetail }) => void;
    jest.mocked(getTourDetail).mockImplementationOnce(
      () => new Promise<{ data: TourDetail }>((resolve) => { resolveCurrent = resolve; }),
    );
    mockQuery = `tour=rome-food-walk&date=${FUTURE_DATE}&participants=`;
    rerender(<BookingForm locale="en" todayUtc={TODAY_UTC} />);

    expect(screen.queryByRole('button', { name: 'Confirm & Pay' })).not.toBeInTheDocument();
    expect(createBooking).not.toHaveBeenCalled();

    resolveCurrent(bookingTour());
    expect(await screen.findByRole('button', { name: 'Confirm & Pay' })).toBeDisabled();
    expect(getTourDetail).toHaveBeenCalledTimes(2);
    expect(createBooking).not.toHaveBeenCalled();
  });

  // An older response must not overwrite the newer query's accepted facts.
  it('ignores an older out-of-order response after a newer query resolved', async () => {
    const { rerender } = renderForm();
    expect(await screen.findByText('Hidden Gems of Rome Walking Tour')).toBeInTheDocument();

    // B starts loading but stays pending.
    let resolveB!: (value: { data: TourDetail }) => void;
    jest.mocked(getTourDetail).mockImplementationOnce(
      () => new Promise<{ data: TourDetail }>((resolve) => { resolveB = resolve; }),
    );
    mockQuery = `tour=b-course-walk&date=${FUTURE_DATE}&participants=3`;
    rerender(<BookingForm locale="en" todayUtc={TODAY_UTC} />);
    await waitFor(() => {
      expect(screen.queryByText('Hidden Gems of Rome Walking Tour')).not.toBeInTheDocument();
    });

    // C resolves promptly on its own fetch and becomes the accepted selection.
    jest.mocked(getTourDetail).mockResolvedValueOnce(bookingTour({
      slug: 'c-course-walk',
      title: 'C Course Walk',
      group_size: { min: 1, max: 4 },
      pricing: {
        base_price: { amount: 7000, currency: 'EUR', formatted: '€70.00' },
        tiered_pricing: null,
      },
    }));
    mockQuery = `tour=c-course-walk&date=${FUTURE_DATE}&participants=1`;
    rerender(<BookingForm locale="en" todayUtc={TODAY_UTC} />);
    expect(await screen.findByText('C Course Walk')).toBeInTheDocument();
    expect(screen.getByText('€70.00 / person')).toBeInTheDocument();

    // B resolves late: C's accepted tour, count, and quote persist.
    resolveB(bookingTour({
      slug: 'b-course-walk',
      title: 'B Course Walk',
      group_size: { min: 1, max: 4 },
      pricing: {
        base_price: { amount: 5000, currency: 'EUR', formatted: '€50.00' },
        tiered_pricing: null,
      },
    }));
    await waitFor(() => {
      expect(getTourDetail).toHaveBeenCalledTimes(3);
    });
    expect(screen.getByText('C Course Walk')).toBeInTheDocument();
    expect(screen.queryByText('B Course Walk')).not.toBeInTheDocument();
    expect(screen.getByText('€70.00 / person')).toBeInTheDocument();
    expect(screen.queryByText('€150.00')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Confirm & Pay' }));
    await waitFor(() => {
      expect(createBooking).toHaveBeenCalledTimes(1);
    });
    expect(jest.mocked(createBooking).mock.calls[0][0]).toMatchObject({
      tour_slug: 'c-course-walk',
      participant_count: 1,
    });
  });
});
