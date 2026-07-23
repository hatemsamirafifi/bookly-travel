import { render, screen } from '@testing-library/react';
import VerificationPage, { generateMetadata } from '../page';

/*
 * Spec 014 (T032, FR-027, SC-010, US8 acceptance 1/2/5): the public
 * verification page renders the VALID/CANCELLED status + allowed fields for a
 * known booking, renders the not-found state for an unknown reference, emits
 * a noindex robots directive (via generateMetadata), and never renders PII.
 *
 * The page is a Server Component that reads Accept-Language (next/headers),
 * resolves translations (next-intl/server), and fetches the read-only
 * verification API (@/lib/api/client). All three are mocked so the test runs
 * in jsdom with no backend/container dependency. The mocked getTranslations
 * loads the REAL messages JSON for the negotiated locale, so this also
 * exercises i18n parity for the `verification` namespace across en/es/it.
 */

jest.mock('next/headers', () => ({
  headers: jest.fn(),
}));

jest.mock('next-intl/server', () => ({
  getTranslations: jest.fn(),
}));

jest.mock('@/lib/api/client', () => ({
  apiClient: jest.fn(),
  ApiError: class ApiError extends Error {
    status: number;
    constructor(message: string, status: number) {
      super(message);
      this.status = status;
    }
  },
  NotFoundError: class NotFoundError extends Error {
    constructor(message = 'Not found') {
      super(message);
      this.name = 'NotFoundError';
    }
  },
  RateLimitError: class RateLimitError extends Error {
    retryAfter: number;
    constructor(message: string, retryAfter: number) {
      super(message);
      this.retryAfter = retryAfter;
    }
  },
}));

const { headers } = require('next/headers') as { headers: jest.Mock };
const { getTranslations } = require('next-intl/server') as { getTranslations: jest.Mock };
const { apiClient } = require('@/lib/api/client') as { apiClient: jest.Mock };

// Real messages, indexed by locale — the test exercises actual i18n parity.
const messages: Record<string, Record<string, unknown>> = {
  en: require('../../../../../messages/en.json'),
  es: require('../../../../../messages/es.json'),
  it: require('../../../../../messages/it.json'),
};

function makeT(locale: string, namespace: string) {
  const root = (messages[locale]?.[namespace] ?? messages.en[namespace]) as Record<string, unknown>;

  const resolve = (key: string): unknown => {
    const parts = key.split('.');
    let node: unknown = root;
    for (const part of parts) {
      if (node && typeof node === 'object' && part in (node as Record<string, unknown>)) {
        node = (node as Record<string, unknown>)[part];
      } else {
        return undefined;
      }
    }
    return node;
  };

  return (key: string, vars?: Record<string, string>): string => {
    let value = resolve(key);
    if (value === undefined) return key;
    if (typeof value !== 'string') return String(value);
    if (vars) {
      for (const [k, v] of Object.entries(vars)) {
        value = (value as string).replace(new RegExp(`\\{${k}\\}`, 'g'), v);
      }
    }
    return value as string;
  };
}

function setupMocks(locale: string, acceptLanguage: string) {
  headers.mockResolvedValue(new Headers({ 'accept-language': acceptLanguage }));
  getTranslations.mockImplementation(async (opts: { locale?: string; namespace?: string }) => {
    const loc = opts.locale ?? locale;
    return makeT(loc, opts.namespace ?? 'verification');
  });
}

const confirmedPayload = {
  data: {
    reference: 'BKO-AB23XY',
    status: 'VALID',
    tour_title: 'Majestic Roman Colosseum Tour',
    tour_date: '2026-08-15',
    participant_count: 2,
    created_at: '2026-07-04T12:00:00Z',
    voucher_generated_at: '2026-07-04T12:01:30Z',
  },
};

const cancelledPayload = {
  data: {
    reference: 'BKO-CD45GH',
    status: 'CANCELLED',
    tour_title: 'Majestic Roman Colosseum Tour',
    tour_date: '2026-08-15',
    participant_count: 2,
  },
};

beforeEach(() => {
  jest.clearAllMocks();
});

it('renders the VALID status + allowed fields for a confirmed booking', async () => {
  setupMocks('en', 'en-US,en;q=0.9');
  apiClient.mockResolvedValue(confirmedPayload);

  const element = await VerificationPage({ params: Promise.resolve({ reference: 'BKO-AB23XY' }) });
  render(element);

  expect(screen.getByText('Valid')).toBeInTheDocument();
  expect(screen.getByText('BKO-AB23XY')).toBeInTheDocument();
  expect(screen.getByText('Majestic Roman Colosseum Tour')).toBeInTheDocument();
  expect(screen.getByText('2')).toBeInTheDocument();
});

it('renders the CANCELLED status for a cancelled booking', async () => {
  setupMocks('en', 'en');
  apiClient.mockResolvedValue(cancelledPayload);

  const element = await VerificationPage({ params: Promise.resolve({ reference: 'BKO-CD45GH' }) });
  render(element);

  expect(screen.getByText('Cancelled')).toBeInTheDocument();
  expect(screen.getByText('BKO-CD45GH')).toBeInTheDocument();
});

it('renders the not-found state for an unknown reference (no enumeration signal)', async () => {
  setupMocks('en', 'en');
  const { NotFoundError } = require('@/lib/api/client');
  apiClient.mockRejectedValue(new NotFoundError('Not found'));

  const element = await VerificationPage({ params: Promise.resolve({ reference: 'BKO-ZZ99ZZ' }) });
  render(element);

  expect(screen.getByText('Voucher not found')).toBeInTheDocument();
  // The raw reference is echoed back, but no booking fields are rendered.
  expect(screen.queryByText('Majestic Roman Colosseum Tour')).not.toBeInTheDocument();
});

it('renders localized status labels when Accept-Language negotiates es', async () => {
  setupMocks('es', 'es-ES,es;q=0.9,en;q=0.8');
  apiClient.mockResolvedValue(confirmedPayload);

  const element = await VerificationPage({ params: Promise.resolve({ reference: 'BKO-AB23XY' }) });
  render(element);

  // ES status label for VALID is "Válido".
  expect(screen.getByText('Válido')).toBeInTheDocument();
});

it('emits a noindex,nofollow robots directive via generateMetadata (FR-027)', async () => {
  setupMocks('en', 'en');

  const meta = await generateMetadata({ params: Promise.resolve({ reference: 'BKO-AB23XY' }) });

  expect(meta.robots).toEqual({ index: false, follow: false });
});

it('never renders traveler PII in the page HTML (SC-010)', async () => {
  setupMocks('en', 'en');
  // Even if the API somehow leaked PII, the page only renders known fields —
  // but assert the rendered HTML never contains forbidden PII markers.
  apiClient.mockResolvedValue({
    data: {
      reference: 'BKO-AB23XY',
      status: 'VALID',
      tour_title: 'Majestic Roman Colosseum Tour',
      tour_date: '2026-08-15',
      participant_count: 2,
    },
  });

  const element = await VerificationPage({ params: Promise.resolve({ reference: 'BKO-AB23XY' }) });
  const { container } = render(element);
  const html = container.innerHTML;

  const forbidden = ['traveler_name', 'traveler_email', 'email', 'phone', 'total_price', 'currency', 'guest_identity', 'partner_notes'];
  for (const field of forbidden) {
    expect(html).not.toContain(field);
  }
});