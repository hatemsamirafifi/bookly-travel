import { z } from 'zod';

import type { SearchParams } from '@/lib/api/types';

/**
 * Sort options accepted by the public search/category/destination listings.
 *
 * This enum MUST stay in sync with `SearchParams['sort']` in
 * `src/lib/api/types.ts` (which mirrors the backend sort whitelist). The
 * `_Sync` compile-time guard below fails to compile if the two drift.
 */
export const sortSchema = z.enum([
  'relevance',
  'price_asc',
  'price_desc',
  'rating',
  'newest',
]);

export type Sort = z.infer<typeof sortSchema>;

// Compile-time guard: the zod enum must exactly mirror SearchParams['sort']
// (minus `undefined`). If the backend adds a sort option, update both
// `SearchParams['sort']` and the enum above.
type _ApiSort = Exclude<SearchParams['sort'], undefined>;
type _Sync = Sort extends _ApiSort ? (_ApiSort extends Sort ? true : never) : never;
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const _sync: _Sync = true;

/**
 * Parse an inbound `sort` query-string value into a typed
 * `SearchParams['sort']`.
 *
 * Fail-open by design: `undefined`, `null`, empty, whitespace-only, or any
 * value outside the whitelist returns `undefined`, which causes the param to
 * be omitted from the API request so the backend applies its default
 * (relevance) ordering. This preserves the previous behavior, where empty/odd
 * sort values were simply dropped. This function never throws and never logs.
 */
export function parseSort(raw: string | undefined | null): SearchParams['sort'] {
  if (raw == null) return undefined;
  const trimmed = raw.trim();
  if (!trimmed) return undefined;
  const result = sortSchema.safeParse(trimmed);
  return result.success ? result.data : undefined;
}