import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { getTranslations } from 'next-intl/server';
import { apiClient, ApiError, NotFoundError, RateLimitError } from '@/lib/api/client';

/**
 * Spec 014 (FR-027, FR-028, SC-010/011): public, read-only voucher
 * verification page at the root path `/v/{reference}` — the URL the voucher
 * QR encodes. Lives OUTSIDE the `[locale]` segment (the next-intl middleware
 * matcher excludes `/v`) so the QR URL resolves verbatim, with no redirect to
 * `/{locale}/v/{reference}` that would break a scanned link (FR-002).
 *
 * Server Component. No auth, no dashboard, no navigation to private surfaces.
 * Locale is negotiated from the Accept-Language header (the page is root-level,
 * so there is no `[locale]` segment to read). `noindex,nofollow` everywhere.
 */

const SUPPORTED_LOCALES = ['en', 'es', 'it'] as const;
type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

interface PageProps {
  params: Promise<{ reference: string }>;
}

interface VerificationData {
  reference: string;
  status: string;
  tour_title: string;
  tour_date: string;
  participant_count: number;
  created_at?: string;
  voucher_generated_at?: string;
}

interface VerificationResponse {
  data: VerificationData;
}

// Statically known status set from the contract (verification-api.md). Any
// unrecognized status renders as a neutral "unknown" pill — never as an error.
const STATUS_STYLES: Record<string, string> = {
  VALID: 'bg-green-50 text-green-700 border-green-200',
  CANCELLED: 'bg-red-50 text-red-700 border-red-200',
  PENDING: 'bg-amber-50 text-amber-700 border-amber-200',
  EXPIRED: 'bg-gray-100 text-gray-700 border-gray-200',
};

// force-dynamic: the page reads the Accept-Language header and a booking's
// status can change at any time — it MUST NOT be statically cached or served
// stale (contract: `Cache-Control: no-store` on the API, mirrored here).
export const dynamic = 'force-dynamic';
export const revalidate = 0;

function pickLocale(acceptLanguage: string | null): SupportedLocale {
  if (!acceptLanguage) return 'en';

  // RFC 9110 §12.5.4: parse each language-tag's `q` weight (default 1.0),
  // drop q=0 entries, then pick the highest-q supported base language. This
  // honors "en;q=0.5,es;q=0.9" → es (highest q), whereas a naive first-match
  // would wrongly return en. Lightweight — no parser dependency.
  const candidates = acceptLanguage
    .split(',')
    .map((part) => {
      const segments = part.trim().split(';');
      const tag = (segments[0] ?? '').toLowerCase().trim();
      const qParam = segments.slice(1).find((s) => s.trim().startsWith('q='));
      const q = qParam ? parseFloat(qParam.trim().slice(2)) : 1;
      return { base: tag.split('-')[0], q: Number.isNaN(q) ? 1 : q };
    })
    .filter((c) => c.base && c.q > 0)
    .sort((a, b) => b.q - a.q);

  for (const candidate of candidates) {
    if ((SUPPORTED_LOCALES as readonly string[]).includes(candidate.base)) {
      return candidate.base as SupportedLocale;
    }
  }

  return 'en';
}

function formatDate(iso: string, locale: SupportedLocale): string {
  try {
    return new Intl.DateTimeFormat(locale, {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    }).format(new Date(iso));
  } catch {
    return iso;
  }
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { reference } = await params;
  const acceptLanguage = (await headers()).get('accept-language');
  const locale = pickLocale(acceptLanguage);
  const t = await getTranslations({ locale, namespace: 'verification' });

  return {
    title: t('metaTitle', { reference }),
    description: t('metaDescription'),
    robots: { index: false, follow: false },
  };
}

export default async function VerificationPage({ params }: PageProps) {
  const { reference } = await params;
  const acceptLanguage = (await headers()).get('accept-language');
  const locale = pickLocale(acceptLanguage);
  const t = await getTranslations({ locale, namespace: 'verification' });

  let verification: VerificationData | null = null;
  let state: 'found' | 'notFound' | 'rateLimited' | 'error' = 'notFound';

  try {
    const response = await apiClient<VerificationResponse>(
      `/api/public/v/${encodeURIComponent(reference)}`,
      { locale },
    );
    verification = response.data;
    state = 'found';
  } catch (error) {
    // Malformed and unknown references are indistinguishable here: the API
    // returns 404 for both, so the human viewer sees the same not-found state
    // either way (SC-010/011 — no enumeration signal on the page either).
    if (error instanceof NotFoundError) {
      state = 'notFound';
    } else if (error instanceof RateLimitError) {
      state = 'rateLimited';
    } else if (error instanceof ApiError) {
      state = 'error';
    } else {
      throw error;
    }
  }

  return (
    <main className="flex min-h-screen flex-col items-center bg-gray-50 px-4 py-10">
      <div className="w-full max-w-lg">
        <header className="mb-6 text-center">
          <p className="text-sm font-semibold uppercase tracking-widest text-[#FFB800]">
            {t('brand')}
          </p>
          <h1 className="mt-1 text-2xl font-bold text-[#0A2540]">{t('heading')}</h1>
          <p className="mt-2 text-sm text-gray-500">{t('subtitle')}</p>
        </header>

        {state === 'found' && verification !== null && (
          <FoundCard verification={verification} t={t} locale={locale} />
        )}

        {state === 'notFound' && (
          <StateCard
            tone="gray"
            title={t('notFound.title')}
            description={t('notFound.description')}
            reference={reference}
            t={t}
          />
        )}

        {state === 'rateLimited' && (
          <StateCard
            tone="amber"
            title={t('rateLimited.title')}
            description={t('rateLimited.description')}
            reference={reference}
            t={t}
          />
        )}

        {state === 'error' && (
          <StateCard
            tone="gray"
            title={t('error.title')}
            description={t('error.description')}
            reference={reference}
            t={t}
          />
        )}

        <footer className="mt-6 text-center text-xs text-gray-400">
          <p>{t('legalNote')}</p>
        </footer>
      </div>
    </main>
  );
}

interface CardProps {
  verification: VerificationData;
  t: Awaited<ReturnType<typeof getTranslations>>;
  locale: SupportedLocale;
}

function FoundCard({ verification, t, locale }: CardProps) {
  const statusStyle = STATUS_STYLES[verification.status] ?? 'bg-gray-100 text-gray-700 border-gray-200';

  // Static-key lookups (no dynamic t keys) so the build stays type-safe and a
  // future/unknown status degrades to the raw value instead of a missing-key
  // error. The contract guarantees status ∈ {VALID,CANCELLED,PENDING,EXPIRED}.
  const statusLabels: Record<string, string> = {
    VALID: t('statusLabels.VALID'),
    CANCELLED: t('statusLabels.CANCELLED'),
    PENDING: t('statusLabels.PENDING'),
    EXPIRED: t('statusLabels.EXPIRED'),
  };
  const statusDescriptions: Record<string, string> = {
    VALID: t('statusDescriptions.VALID'),
    CANCELLED: t('statusDescriptions.CANCELLED'),
    PENDING: t('statusDescriptions.PENDING'),
    EXPIRED: t('statusDescriptions.EXPIRED'),
  };
  const statusLabel = statusLabels[verification.status] ?? verification.status;
  const statusDescription = statusDescriptions[verification.status] ?? '';

  return (
    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
      <div className={`flex items-center justify-center border-b px-6 py-8 ${statusStyle}`}>
        <p className="text-4xl font-extrabold tracking-tight">{statusLabel}</p>
      </div>

      <div className="px-6 py-6">
        <dl className="space-y-3 text-sm">
          <Field label={t('fields.reference')} value={verification.reference} mono />
          <Field label={t('fields.tourTitle')} value={verification.tour_title} />
          <Field
            label={t('fields.tourDate')}
            value={formatDate(verification.tour_date, locale)}
          />
          <Field
            label={t('fields.participants')}
            value={String(verification.participant_count)}
          />
          {verification.created_at && (
            <Field
              label={t('fields.createdAt')}
              value={formatDate(verification.created_at, locale)}
            />
          )}
          {verification.voucher_generated_at && (
            <Field
              label={t('fields.voucherGeneratedAt')}
              value={formatDate(verification.voucher_generated_at, locale)}
            />
          )}
        </dl>

        {statusDescription && (
          <p className="mt-5 border-t border-gray-100 pt-4 text-xs text-gray-500">
            {statusDescription}
          </p>
        )}
      </div>
    </div>
  );
}

interface StateCardProps {
  tone: 'gray' | 'amber';
  title: string;
  description: string;
  reference: string;
  t: Awaited<ReturnType<typeof getTranslations>>;
}

function StateCard({ tone, title, description, reference, t }: StateCardProps) {
  const toneStyle =
    tone === 'amber'
      ? 'bg-amber-50 border-amber-200 text-amber-700'
      : 'bg-gray-50 border-gray-200 text-gray-600';

  return (
    <div className={`rounded-2xl border px-6 py-8 text-center ${toneStyle}`}>
      <h2 className="text-xl font-bold">{title}</h2>
      <p className="mt-2 text-sm opacity-90">{description}</p>
      <p className="mt-4 break-all font-mono text-xs opacity-60">{reference}</p>
      <span className="sr-only">{t('legalNote')}</span>
    </div>
  );
}

interface FieldProps {
  label: string;
  value: string;
  mono?: boolean;
}

function Field({ label, value, mono }: FieldProps) {
  return (
    <div className="flex justify-between gap-4">
      <dt className="shrink-0 text-gray-500">{label}</dt>
      <dd className={`text-right text-[#0A2540] ${mono ? 'font-mono' : 'font-medium'}`}>
        {value || '—'}
      </dd>
    </div>
  );
}