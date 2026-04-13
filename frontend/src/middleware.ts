import createMiddleware from 'next-intl/middleware';
import { routing } from './i18n/routing';

export default createMiddleware(routing);

export const config = {
  matcher: [
    // Exclude static assets, API routes, and Next.js internals to ensure all dynamic non-asset routes are matched
    '/((?!api|_next/static|_next/image|favicon.ico|sitemap.xml|robots.txt|.*\\\\..*).*)'
  ]
};
