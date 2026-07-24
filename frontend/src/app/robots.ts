import type { MetadataRoute } from 'next';

// Spec 006 seo-contracts.md FR-031 — robots.txt. The site root is served by
// Next.js (excluded from the api proxy in src/proxy.ts), so this is the
// authoritative robots.txt. Allows the three locale roots, blocks the API
// surface, and points crawlers at the sitemap.
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? 'https://bookly.com';

export default function robots(): MetadataRoute.Robots {
  return {
    rules: {
      userAgent: '*',
      allow: ['/en/', '/es/', '/it/'],
      disallow: ['/api/'],
    },
    sitemap: `${SITE_URL}/sitemap.xml`,
  };
}