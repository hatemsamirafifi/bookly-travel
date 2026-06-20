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
    ],
  },
};

export default withNextIntl(nextConfig);
