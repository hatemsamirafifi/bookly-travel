import type { NextConfig } from 'next';
import createNextIntlPlugin from 'next-intl/plugin';

const withNextIntl = createNextIntlPlugin('./src/i18n/request.ts');

const nextConfig: NextConfig = {
  reactStrictMode: true,
  turbopack: {
    root: process.cwd(),
  },
  // Allow the dev server to accept requests/HMR from the Docker service hostnames
  // used in-container. When Playwright runs inside the bookly-frontend container it
  // browses the app via the nginx reverse proxy (http://nginx) so the page and /api
  // share one origin (no CORS); nginx proxies the HMR WebSocket to this dev server
  // with Host=nginx, which is not localhost, so it must be explicitly allowed.
  allowedDevOrigins: ['nginx', 'nextjs', 'localhost'],
  images: {
    remotePatterns: [
      { protocol: 'https', hostname: '**' },
      // Local fixture CDN (http only inside Docker; see docker/nginx/nginx.conf).
      { protocol: 'http', hostname: 'cdn.bookly.test' },
    ],
    // The fixture CDN resolves to a private Docker-network IP. The optimizer
    // refuses private upstream IPs by default, so local Docker stacks opt in
    // via ALLOW_PRIVATE_IMAGE_UPSTREAM=true (see docker-compose.yml). The
    // flag is a no-op for public-IP upstreams and defaults to false, so
    // production (real CDN on a public IP) keeps the strict default.
    // remotePatterns still bound WHICH hosts may be fetched.
    dangerouslyAllowLocalIP: process.env.ALLOW_PRIVATE_IMAGE_UPSTREAM === 'true',
  },
  async rewrites() {
    return [
      {
        // F-SEO-01: serve the backend-generated sitemap on the frontend origin
        // (/sitemap.xml) via the internal API host. Must stay BEFORE the generic
        // '/api/:path*' rule. The proxy matcher excludes sitemap.xml from the
        // next-intl middleware (see src/proxy.ts) so no locale redirect fires.
        source: '/sitemap.xml',
        destination: `${process.env.API_INTERNAL_URL || 'http://nginx'}/api/public/sitemap.xml`,
      },
      {
        source: '/api/:path*',
        destination: `${process.env.API_INTERNAL_URL || 'http://nginx'}/api/:path*`,
      },
    ];
  },
};

export default withNextIntl(nextConfig);
