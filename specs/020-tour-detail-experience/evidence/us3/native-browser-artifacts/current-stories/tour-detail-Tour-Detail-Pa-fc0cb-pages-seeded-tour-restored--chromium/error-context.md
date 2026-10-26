# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> US4 public SSR shows real cover-first gallery with EN alt lang=en on ES/IT pages (seeded tour, restored)
- Location: tests\e2e\tour-detail.spec.ts:505:7

# Error details

```
Error: Missing partner auth_token in localStorage
```

# Test source

```ts
  1   | import type { Page } from '@playwright/test';
  2   | import type { PartnerTourMediaInput } from '@/lib/api/types';
  3   | 
  4   | /**
  5   |  * Bounded Spec 019 US1 real-API helpers for partner browser tests.
  6   |  *
  7   |  * All helpers drive the REAL backend through the same origin the page was
  8   |  * loaded from (nginx in the isolated browser stack) using the partner
  9   |  * bearer token already present in localStorage (seeded partner@bookly.test
  10  |  * via the `setup` storage state). No route mocks: every status below is an
  11  |  * actual API response. Tokens are read at call time and never written to
  12  |  * logs, screenshots or reports.
  13  |  */
  14  | 
  15  | export interface ApiResult {
  16  |   status: number;
  17  |   json: unknown;
  18  | }
  19  | 
  20  | function apiOrigin(page: Page): string {
  21  |   return new URL(page.url()).origin;
  22  | }
  23  | 
  24  | export async function partnerToken(page: Page): Promise<string> {
  25  |   const token = await page.evaluate(() => localStorage.getItem('auth_token'));
> 26  |   if (!token) throw new Error('Missing partner auth_token in localStorage');
      |                     ^ Error: Missing partner auth_token in localStorage
  27  |   return token;
  28  | }
  29  | 
  30  | async function api(page: Page, method: string, path: string, body?: unknown): Promise<ApiResult> {
  31  |   const token = await partnerToken(page);
  32  |   const response = await page.request.fetch(`${apiOrigin(page)}${path}`, {
  33  |     method,
  34  |     headers: {
  35  |       Authorization: `Bearer ${token}`,
  36  |       Accept: 'application/json',
  37  |       'Content-Type': 'application/json',
  38  |     },
  39  |     data: body === undefined ? undefined : JSON.stringify(body),
  40  |   });
  41  |   const json = await response.json().catch(() => null);
  42  |   return { status: response.status(), json };
  43  | }
  44  | 
  45  | export const apiGet = (page: Page, path: string): Promise<ApiResult> => api(page, 'GET', path);
  46  | export const apiPost = (page: Page, path: string, body: unknown): Promise<ApiResult> =>
  47  |   api(page, 'POST', path, body);
  48  | export const apiPut = (page: Page, path: string, body: unknown): Promise<ApiResult> =>
  49  |   api(page, 'PUT', path, body);
  50  | 
  51  | /** Unique-per-run title so reruns never collide on seeded data. */
  52  | export function uniqueTitle(prefix: string): string {
  53  |   return `${prefix} ${Date.now().toString(36)}`;
  54  | }
  55  | 
  56  | export const LONG_DESCRIPTION =
  57  |   'A wonderful guided experience through historic streets with a local expert, visiting celebrated landmarks, hidden corners and the stories behind the city charm and living culture.';
  58  | 
  59  | /** Full nine-field English source payload for real create requests. */
  60  | export function fullEnglishSource(title: string) {
  61  |   return {
  62  |     title,
  63  |     description: LONG_DESCRIPTION,
  64  |     category: 'walking',
  65  |     destination: 'Rome, Italy',
  66  |     duration_value: 3,
  67  |     duration_unit: 'hour',
  68  |     difficulty_level: 'easy',
  69  |     meeting_point: 'Colosseum Main Entrance',
  70  |     cancellation_policy: 'Cancel up to 24 hours in advance for a full refund.',
  71  |     highlights: ['Old-town route'],
  72  |     inclusions: ['Live guide'],
  73  |     exclusions: ['Lunch'],
  74  |     important_information: ['Comfortable shoes recommended'],
  75  |     translations: {
  76  |       en: {
  77  |         title,
  78  |         description: LONG_DESCRIPTION,
  79  |         highlights: ['Old-town route'],
  80  |         inclusions: ['Live guide'],
  81  |         exclusions: ['Lunch'],
  82  |         important_information: ['Comfortable shoes recommended'],
  83  |         meeting_point: 'Colosseum Main Entrance',
  84  |         cancellation_policy: 'Cancel up to 24 hours in advance for a full refund.',
  85  |         itinerary: [
  86  |           {
  87  |             day: 1,
  88  |             title: 'Arrival',
  89  |             description: null,
  90  |             stops: [
  91  |               { title: 'Meet the guide', description: null, duration_minutes: 30 },
  92  |               { title: 'Old town walk', description: null, duration_minutes: null },
  93  |             ],
  94  |           },
  95  |           { day: 2, title: 'Explore', description: null, stops: [] },
  96  |         ],
  97  |       },
  98  |     },
  99  |   };
  100 | }
  101 | 
  102 | function isRecord(value: unknown): value is Record<string, unknown> {
  103 |   return typeof value === 'object' && value !== null;
  104 | }
  105 | 
  106 | /** Resolve the actual seeded owned tour; sequences need not start at one.
  107 |  * Call after loading a partner page so the bearer token has its real origin.
  108 |  */
  109 | export async function seededOwnedTourId(page: Page): Promise<number> {
  110 |   const response = await page.request.get('/api/public/tours/hidden-gems-rome-walking-tour?locale=en');
  111 |   const body: unknown = await response.json();
  112 |   if (response.status() !== 200 || !isRecord(body) || !isRecord(body.data)
  113 |     || typeof body.data.id !== 'number' || body.data.id <= 0) {
  114 |     throw new Error('Missing real seeded tour identity');
  115 |   }
  116 |   const owned = await apiGet(page, `/api/partner/tours/${body.data.id}`);
  117 |   if (owned.status !== 200) throw new Error('Seeded tour is not owned by the authenticated partner');
  118 |   return body.data.id;
  119 | }
  120 | 
  121 | /** Extract the owned EN translation row from a partner detail GET body. */
  122 | export function ownedEnRow(detailJson: unknown): Record<string, unknown> {
  123 |   const data = isRecord(detailJson) && isRecord(detailJson.data) ? detailJson.data : null;
  124 |   if (!data || !Array.isArray(data.translations)) throw new Error('Missing tour translations in GET body');
  125 |   const en = (data.translations as unknown[]).find(
  126 |     (row): row is Record<string, unknown> => isRecord(row) && row.locale === 'en'
```