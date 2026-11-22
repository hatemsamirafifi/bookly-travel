import { Suspense } from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import PricingPage from '../page';
import enMessages from '../../../../../../../../../messages/en.json';
import esMessages from '../../../../../../../../../messages/es.json';
import itMessages from '../../../../../../../../../messages/it.json';
import {
  getPartnerPricing,
  syncPartnerPricingTiers,
  deletePartnerPricingTier,
} from '@/lib/api/partner';

let currentLocale: 'en' | 'es' | 'it' = 'en';

function resolvePath(root: unknown, dotted: string): unknown {
  return dotted.split('.').reduce<unknown>((node, key) => {
    if (node && typeof node === 'object') {
      return (node as Record<string, unknown>)[key];
    }
    return undefined;
  }, root);
}

function formatMessage(template: string, values?: Record<string, string | number>): string {
  if (!values) return template;
  let output = template.replace(
    /\{(\w+),\s*plural,\s*=1\s*\{([^}]*)\}\s*other\s*\{([^}]*)\}\}/g,
    (_, key: string, one: string, other: string) => {
      const count = Number(values[key] ?? 0);
      return (count === 1 ? one : other).replace('#', String(count));
    },
  );
  for (const [k, v] of Object.entries(values)) {
    output = output.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v));
  }
  return output;
}

const translatorCache = new Map<string, (key: string, values?: Record<string, string | number>) => string>();
jest.mock('next-intl', () => ({
  useTranslations: (namespace?: string) => {
    const cacheKey = `${currentLocale}:${namespace ?? ''}`;
    let fn = translatorCache.get(cacheKey);
    if (!fn) {
      fn = (key: string, values?: Record<string, string | number>) => {
        const catalogs = { en: enMessages, es: esMessages, it: itMessages };
        const root = catalogs[currentLocale];
        const fullKey = namespace ? `${namespace}.${key}` : key;
        const raw = resolvePath(root, fullKey);
        return typeof raw === 'string' ? formatMessage(raw, values) : fullKey;
      };
      translatorCache.set(cacheKey, fn);
    }
    return fn;
  },
}));

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => (
    <a href={href} {...rest}>{children}</a>
  ),
}));

jest.mock('@/lib/api/partner', () => ({
  getPartnerPricing: jest.fn(),
  syncPartnerPricingTiers: jest.fn(),
  createPartnerPricingTier: jest.fn(),
  updatePartnerPricingTier: jest.fn(),
  deletePartnerPricingTier: jest.fn(),
}));

async function renderPricingPage(locale: 'en' | 'es' | 'it' = 'en') {
  currentLocale = locale;
  const params = Promise.resolve({ id: '42', locale });
  await act(async () => {
    render(
      <Suspense fallback={<div>Suspending...</div>}>
        <PricingPage params={params} />
      </Suspense>,
    );
  });
}

describe('Partner Tour Pricing Page (EGM-005 D5)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    currentLocale = 'en';
    jest.mocked(getPartnerPricing).mockResolvedValue({
      data: [
        {
          id: 1,
          tour_id: 42,
          tour_option_id: 10,
          name: 'Solo/Duo',
          price: '80.00',
          price_amount: 8000,
          currency: 'EUR',
          min_participants: 1,
          max_participants: 2,
          sort_order: 1,
        },
        {
          id: 2,
          tour_id: 42,
          tour_option_id: 10,
          name: 'Group',
          price: '60.00',
          price_amount: 6000,
          currency: 'EUR',
          min_participants: 3,
          max_participants: 6,
          sort_order: 2,
        },
        {
          id: 9,
          tour_id: 42,
          tour_option_id: null,
          name: 'Legacy Adult',
          price: '50.00',
          price_amount: 5000,
          currency: 'EUR',
          min_participants: 1,
          max_participants: 10,
          sort_order: 0,
        },
      ],
      meta: {
        mode: 'per_person',
        options: [
          {
            id: 10,
            title: 'Colosseum Morning',
            effective_capacity: 6,
            price_amount: 8000,
            currency: 'EUR',
          },
        ],
      },
    });
    jest.mocked(syncPartnerPricingTiers).mockResolvedValue({ data: [], message: 'ok' });
    jest.mocked(deletePartnerPricingTier).mockResolvedValue({ message: 'deleted' });
  });

  it('renders localized pricing management headings in en, es, and it', async () => {
    await renderPricingPage('en');
    expect(await screen.findByRole('heading', { name: 'Tour Pricing Management' })).toBeInTheDocument();
    expect(screen.getByText('Pricing mode: Per person only')).toBeInTheDocument();

    await renderPricingPage('es');
    expect(await screen.findByRole('heading', { name: 'Gestión de precios del tour' })).toBeInTheDocument();

    await renderPricingPage('it');
    expect(await screen.findByRole('heading', { name: 'Gestione prezzi del tour' })).toBeInTheDocument();
  });

  it('syncs option schedule with exact integer cents, per_person mode, and updates live traveler preview', async () => {
    await renderPricingPage('en');
    await screen.findByDisplayValue('Solo/Duo');

    const priceInputs = screen.getAllByLabelText('Price per person (EUR)');
    fireEvent.change(priceInputs[0], { target: { value: '49.99' } });

    expect(screen.getAllByText('€49.99 / person').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Total: €99.98')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Save tier schedule' }));

    await waitFor(() => expect(syncPartnerPricingTiers).toHaveBeenCalledTimes(1));
    expect(syncPartnerPricingTiers).toHaveBeenCalledWith('42', {
      tour_option_id: 10,
      pricing_mode: 'per_person',
      tiers: [
        { name: 'Solo/Duo', min_participants: 1, max_participants: 2, price_amount: 4999, currency: 'EUR' },
        { name: 'Group', min_participants: 3, max_participants: 6, price_amount: 6000, currency: 'EUR' },
      ],
    });
    expect(await screen.findByRole('status')).toHaveTextContent('Option pricing tier schedule saved.');
  });

  it('surfaces backend 422 validation errors in an accessible alert', async () => {
    jest.mocked(syncPartnerPricingTiers).mockRejectedValueOnce({
      message: 'Pricing tiers must be contiguous with no gaps or overlaps.',
    });

    await renderPricingPage('en');
    await screen.findByDisplayValue('Solo/Duo');
    fireEvent.click(screen.getByRole('button', { name: 'Save tier schedule' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Pricing tiers must be contiguous with no gaps or overlaps.',
    );
  });

  it('supports inline legacy tour-tier deletion without window.confirm', async () => {
    const confirmSpy = jest.spyOn(window, 'confirm');

    await renderPricingPage('en');
    fireEvent.click(await screen.findByRole('tab', { name: 'Legacy Tour Tiers' }));

    expect(await screen.findByText('Legacy Adult')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
    expect(screen.getByText('Delete this pricing tier?')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Confirm delete' }));
    await waitFor(() => expect(deletePartnerPricingTier).toHaveBeenCalledWith('42', 9));
    expect(confirmSpy).not.toHaveBeenCalled();

    confirmSpy.mockRestore();
  });
});
