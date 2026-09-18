import { notFound } from 'next/navigation';

// Catch-all for any unmatched subroute under `/[locale]/*` (e.g.
// `/en/completely-invalid-page`). Specific routes take precedence, so this
// only fires when nothing else matches — and delegates to the localized
// `[locale]/not-found.tsx` boundary above.
export default function LocaleCatchAll() {
  notFound();
}
