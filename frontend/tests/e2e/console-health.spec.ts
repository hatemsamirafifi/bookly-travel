import { test, expect } from '@playwright/test';

const PAGES = [
  '/en',
  '/en/search?q=rome',
  '/en/tours/hidden-gems-rome-walking-tour',
  '/en/categories',
  '/en/destinations',
  '/en/blog',
  '/en/auth/login',
  '/en/auth/register',
  '/en/privacy',
  '/en/terms',
  '/es',
  '/it',
];

test.describe('Browser console/network health', () => {
  for (const path of PAGES) {
    test(`no unexpected console errors on ${path}`, async ({ page }) => {
      test.setTimeout(120000); // dev-mode cold compiles can exceed the default 60s
      const errors: string[] = [];

      // Track failed HTTP responses by URL so genuinely external hosts can
      // be excluded precisely (console messages do not carry URLs). The
      // seeded fixture CDN (cdn.bookly.test) is intentionally NOT excluded:
      // it resolves inside Docker to real fixture files, so any failure
      // there is a real regression.
      const failedUrls: string[] = [];
      page.on('response', (resp) => {
        if (resp.status() >= 400) failedUrls.push(`${resp.status()} ${resp.url()}`);
      });

      page.on('console', (msg) => {
        if (msg.type() === 'error') {
          const location = msg.location();
          errors.push(`${msg.text()} @ ${location.url}`);
        }
      });
      page.on('pageerror', (err) => errors.push(`pageerror: ${err.message}`));
      page.on('requestfailed', (req) => {
        if (req.failure()?.errorText !== 'net::ERR_ABORTED') {
          errors.push(`requestfailed: ${req.url()} ${req.failure()?.errorText}`);
        }
      });

      const resp = await page.goto(path, { waitUntil: 'load', timeout: 60000 });
      expect(resp?.status()).toBeLessThan(400);
      await page.waitForTimeout(3000);

      const isKnownEnvNoise = (url: string) =>
        url.includes('images.unsplash.com') ||
        url.includes('favicon');

      const badResponses = failedUrls.filter((f) => !isKnownEnvNoise(f));
      expect(
        badResponses,
        `failed responses on ${path}:\n${badResponses.join('\n')}`
      ).toHaveLength(0);

      // Console-level errors: exclude dev noise and errors whose console
      // location points at the known placeholder hosts / favicon.
      const real = errors.filter((e) =>
        !isKnownEnvNoise(e) &&
        !e.includes('Download the React DevTools') &&
        !e.includes('sourcemap') &&
        !e.includes('SourceMap') &&
        !e.includes('net::ERR_ABORTED')
      );
      expect(real, `console errors on ${path}:\n${real.join('\n')}`).toHaveLength(0);
    });
  }
});
