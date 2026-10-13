import type { Page } from '@playwright/test';
import type { PartnerTourMediaInput } from '@/lib/api/types';

/**
 * Bounded Spec 019 US1 real-API helpers for partner browser tests.
 *
 * All helpers drive the REAL backend through the same origin the page was
 * loaded from (nginx in the isolated browser stack) using the partner
 * bearer token already present in localStorage (seeded partner@bookly.test
 * via the `setup` storage state). No route mocks: every status below is an
 * actual API response. Tokens are read at call time and never written to
 * logs, screenshots or reports.
 */

export interface ApiResult {
  status: number;
  json: unknown;
}

function apiOrigin(page: Page): string {
  return new URL(page.url()).origin;
}

export async function partnerToken(page: Page): Promise<string> {
  const token = await page.evaluate(() => localStorage.getItem('auth_token'));
  if (!token) throw new Error('Missing partner auth_token in localStorage');
  return token;
}

async function api(page: Page, method: string, path: string, body?: unknown): Promise<ApiResult> {
  const token = await partnerToken(page);
  const response = await page.request.fetch(`${apiOrigin(page)}${path}`, {
    method,
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    data: body === undefined ? undefined : JSON.stringify(body),
  });
  const json = await response.json().catch(() => null);
  return { status: response.status(), json };
}

export const apiGet = (page: Page, path: string): Promise<ApiResult> => api(page, 'GET', path);
export const apiPost = (page: Page, path: string, body: unknown): Promise<ApiResult> =>
  api(page, 'POST', path, body);
export const apiPut = (page: Page, path: string, body: unknown): Promise<ApiResult> =>
  api(page, 'PUT', path, body);

/** Unique-per-run title so reruns never collide on seeded data. */
export function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now().toString(36)}`;
}

export const LONG_DESCRIPTION =
  'A wonderful guided experience through historic streets with a local expert, visiting celebrated landmarks, hidden corners and the stories behind the city charm and living culture.';

/** Full nine-field English source payload for real create requests. */
export function fullEnglishSource(title: string) {
  return {
    title,
    description: LONG_DESCRIPTION,
    category: 'walking',
    destination: 'Rome, Italy',
    duration_value: 3,
    duration_unit: 'hour',
    difficulty_level: 'easy',
    meeting_point: 'Colosseum Main Entrance',
    cancellation_policy: 'Cancel up to 24 hours in advance for a full refund.',
    highlights: ['Old-town route'],
    inclusions: ['Live guide'],
    exclusions: ['Lunch'],
    important_information: ['Comfortable shoes recommended'],
    translations: {
      en: {
        title,
        description: LONG_DESCRIPTION,
        highlights: ['Old-town route'],
        inclusions: ['Live guide'],
        exclusions: ['Lunch'],
        important_information: ['Comfortable shoes recommended'],
        meeting_point: 'Colosseum Main Entrance',
        cancellation_policy: 'Cancel up to 24 hours in advance for a full refund.',
        itinerary: [
          {
            day: 1,
            title: 'Arrival',
            description: null,
            stops: [
              { title: 'Meet the guide', description: null, duration_minutes: 30 },
              { title: 'Old town walk', description: null, duration_minutes: null },
            ],
          },
          { day: 2, title: 'Explore', description: null, stops: [] },
        ],
      },
    },
  };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

/** Resolve the actual seeded owned tour; sequences need not start at one.
 * Call after loading a partner page so the bearer token has its real origin.
 */
export async function seededOwnedTourId(page: Page): Promise<number> {
  const response = await page.request.get('/api/public/tours/hidden-gems-rome-walking-tour?locale=en');
  const body: unknown = await response.json();
  if (response.status() !== 200 || !isRecord(body) || !isRecord(body.data)
    || typeof body.data.id !== 'number' || body.data.id <= 0) {
    throw new Error('Missing real seeded tour identity');
  }
  const owned = await apiGet(page, `/api/partner/tours/${body.data.id}`);
  if (owned.status !== 200) throw new Error('Seeded tour is not owned by the authenticated partner');
  return body.data.id;
}

/** Extract the owned EN translation row from a partner detail GET body. */
export function ownedEnRow(detailJson: unknown): Record<string, unknown> {
  const data = isRecord(detailJson) && isRecord(detailJson.data) ? detailJson.data : null;
  if (!data || !Array.isArray(data.translations)) throw new Error('Missing tour translations in GET body');
  const en = (data.translations as unknown[]).find(
    (row): row is Record<string, unknown> => isRecord(row) && row.locale === 'en'
  );
  if (!en) throw new Error('Missing EN translation row in GET body');
  return en;
}

export function ownedEnglishSource(detailJson: unknown): Record<string, unknown> {
  const en = ownedEnRow(detailJson);
  return Object.fromEntries([
    'title', 'description', 'highlights', 'inclusions', 'exclusions',
    'meeting_point', 'cancellation_policy', 'itinerary', 'important_information',
  ].map((field) => [field, en[field]]));
}

/** Bounded Spec 019 US4 typed guards (no any/ts-ignore). */

export interface OwnedMediaRow {
  url: string;
  is_cover: boolean;
  sort_order: number;
  alt_text?: string | null;
  alt_locale?: string | null;
}

interface StoredMediaRow {
  url: string;
  type: string;
  sort_order: number;
  alt_text?: string | null;
}

function isStoredMediaRow(value: unknown): value is StoredMediaRow {
  return (
    isRecord(value) &&
    typeof value.url === 'string' &&
    typeof value.type === 'string' &&
    typeof value.sort_order === 'number' &&
    (value.alt_text === undefined || value.alt_text === null || typeof value.alt_text === 'string')
  );
}

/** Ordered partner media rows from a partner detail GET body. */
export function ownedMedia(detailJson: unknown): OwnedMediaRow[] {
  const data = isRecord(detailJson) && isRecord(detailJson.data) ? detailJson.data : null;
  if (!data || !Array.isArray(data.media)) throw new Error('Missing tour media in GET body');
  return data.media.map((row: unknown) => {
    if (!isStoredMediaRow(row)) throw new Error('Unexpected stored tour media shape');
    return {
      ...row,
      is_cover: row.type === 'cover' || (row.type === 'image' && row.url === data.cover_image_url),
    };
  });
}

/** Media URLs in stored order. */
export function ownedMediaUrls(detailJson: unknown): string[] {
  return ownedMedia(detailJson).map((row) => row.url);
}

/** Cover URL from a partner detail GET body (null when absent). */
export function ownedCoverUrl(detailJson: unknown): string | null {
  const data = isRecord(detailJson) && isRecord(detailJson.data) ? detailJson.data : null;
  if (!data) throw new Error('Missing tour data in GET body');
  return typeof data.cover_image_url === 'string' ? data.cover_image_url : null;
}

/** Nullable authoritative difficulty (never invented by these helpers). */
export function ownedDifficulty(detailJson: unknown): string | null {
  const data = isRecord(detailJson) && isRecord(detailJson.data) ? detailJson.data : null;
  if (!data) throw new Error('Missing tour data in GET body');
  return typeof data.difficulty_level === 'string' ? data.difficulty_level : null;
}

/** Spoken guide codes from a partner detail GET body (empty when absent). */
export function ownedGuideLanguages(detailJson: unknown): string[] {
  const data = isRecord(detailJson) && isRecord(detailJson.data) ? detailJson.data : null;
  if (!data) throw new Error('Missing tour data in GET body');
  const codes = data.guide_languages;
  if (!Array.isArray(codes)) return [];
  return codes.filter((code): code is string => typeof code === 'string');
}

/** Build an ordered media write payload (cover first by default). */
export function buildOrderedMedia(
  count: number,
  prefix: string,
  opts?: { altText?: (index: number) => string | null }
): PartnerTourMediaInput[] {
  return Array.from({ length: count }, (_, index) => ({
    url: `https://cdn.example.com/${prefix}-${index}.jpg`,
    is_cover: index === 0,
    ...(opts ? { alt_text: opts.altText ? opts.altText(index) : null } : {}),
  }));
}
