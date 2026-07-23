import createMiddleware from 'next-intl/middleware';
import { routing } from './i18n/routing';

export default createMiddleware(routing);

export const config = {
  matcher: [
    // Exclude static assets, API routes, and Next.js internals to ensure all dynamic non-asset routes are matched.
    // Spec 014 (FR-002, FR-027): exclude `/v/*` so the public voucher
    // verification page at /v/{reference} resolves verbatim — next-intl's
    // `localePrefix: 'always'` would otherwise 307-redirect it to
    // /{locale}/v/{reference}, breaking the QR-encoded URL. The exclusion is
    // anchored to the `v/` segment so other v-prefixed routes (/verify,
    // /videos, /vouchers, …) keep flowing through next-intl. (Next 16 renamed
    // middleware.ts → proxy.ts; the /v exclusion lives here, the authoritative
    // proxy file.)
    '/((?!v/|api|_next/static|_next/image|favicon.ico|sitemap.xml|robots.txt|.*\\\\..*).*)',
  ],
};
