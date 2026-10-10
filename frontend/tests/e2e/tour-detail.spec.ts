import { test, expect } from '@playwright/test';
import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import type { AddressInfo } from 'node:net';
import type { TourDetail } from '@/lib/api/types';

async function freePort(): Promise<number> {
  const probe = createServer();
  await new Promise<void>((resolve) => probe.listen(0, '127.0.0.1', resolve));
  const port = (probe.address() as AddressInfo).port;
  await new Promise<void>((resolve) => probe.close(() => resolve()));
  return port;
}

interface SsrHarness {
  mockPort: number;
  nextPort: number;
  stop: () => Promise<void>;
}

// Local SSR harness (Spec 019 US2): page routes fetch from the server during
// SSR, so browser route mocking cannot intercept them. An isolated Next
// process plus a local API fixture server exercise the real page/metadata
// path without modifying shared tour data. The isolated Next server-fetches
// through API_INTERNAL_URL on every load because the detail fetch uses
// explicit no-store.
async function startSsrHarness(slug: string, readCurrent: () => TourDetail): Promise<SsrHarness> {
  const mock = createServer((req, res) => {
    if (!req.url?.startsWith(`/api/public/tours/${slug}`)) {
      res.writeHead(404).end();
      return;
    }
    res.writeHead(200, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
    res.end(JSON.stringify({ data: readCurrent() }));
  });
  await new Promise<void>((resolve) => mock.listen(0, '127.0.0.1', resolve));
  const mockPort = (mock.address() as AddressInfo).port;
  const nextPort = await freePort();
  const next = spawn(process.execPath, ['node_modules/next/dist/bin/next', 'start', '-p', String(nextPort), '-H', '127.0.0.1'], {
    cwd: process.cwd(),
    env: { ...process.env, API_INTERNAL_URL: `http://127.0.0.1:${mockPort}`, NEXT_TELEMETRY_DISABLED: '1' },
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  let startupOutput = '';
  next.stdout.on('data', (chunk: Buffer) => { startupOutput += chunk.toString(); });
  next.stderr.on('data', (chunk: Buffer) => { startupOutput += chunk.toString(); });
  await expect.poll(async () => {
    if (next.exitCode !== null) throw new Error(`Isolated Next server exited: ${startupOutput}`);
    try { return (await fetch(`http://127.0.0.1:${nextPort}/favicon.ico`)).status; } catch { return 0; }
  }, { timeout: 30000 }).not.toBe(0);
  return {
    mockPort,
    nextPort,
    stop: async () => {
      next.kill('SIGTERM');
      await new Promise<void>((resolve) => mock.close(() => resolve()));
    },
  };
}

// The actual boundary response the isolated Next server consumes. Asserting
// the served JSON (rather than the test's own fixture object) proves what
// SSR actually rendered the fallback/ready transition from.
async function readServedDetail(mockPort: number, slug: string, locale: string): Promise<TourDetail> {
  const response = await fetch(`http://127.0.0.1:${mockPort}/api/public/tours/${slug}?locale=${locale}`);
  expect(response.status).toBe(200);
  return ((await response.json()).data) as TourDetail;
}

test.describe('Tour Detail Page', () => {
  test('ES/IT SSR pages replace disclosed English fallback with ready localized content', async ({ page, request }) => {
    test.setTimeout(120000);
    const slug = 'hidden-gems-rome-walking-tour';
    const fixtureResponse = await request.get(`/api/public/tours/${slug}?locale=en`);
    expect(fixtureResponse.ok()).toBeTruthy();
    const fixture = (await fixtureResponse.json()).data as TourDetail;
    let current = structuredClone(fixture);
    const harness = await startSsrHarness(slug, () => current);
    const { mockPort, nextPort } = harness;

    try {
      for (const scenario of [
        { locale: 'es' as const, title: 'Título español listo', day: 'Llegada española', notice: 'El contenido se muestra en inglés', itineraryNotice: 'El itinerario se muestra en inglés', guide: 'Guía en vivo' },
        { locale: 'it' as const, title: 'Titolo italiano pronto', day: 'Arrivo italiano', notice: 'Il contenuto è mostrato in inglese', itineraryNotice: "L'itinerario è mostrato in inglese", guide: 'Guida dal vivo' },
      ]) {
        const source = structuredClone(fixture);
        source.title = 'Canonical English source';
        source.description = 'The current English source remains visible until translation is ready.';
        source.content_locale = 'en';
        source.itinerary_locale = 'en';
        source.itinerary = [{ day: 1, title: 'English arrival', stops: [{ title: 'Meet the guide' }] }];
        source.guide_languages = ['de', 'en', 'es'];
        source.languages = source.guide_languages;
        source.translation_status = 'pending';
        source.translation_warning = 'partial_translation';
        source.images = [];
        source.operator = null;
        source.related_tours = [];
        source.reviews = { average_rating: 0, count: 0, distribution: {} };
        source.rating = { average: 0, count: 0 };
        source.availability = { ...source.availability, available_dates: [], next_available_date: null, is_unavailable: true };
        source.seo = { ...source.seo, meta_title: `${source.title} | Bookly`, meta_description: source.description };
        current = source;

        // Boundary response the isolated Next server actually consumes.
        const servedFallback = await readServedDetail(mockPort, slug, scenario.locale);
        expect(servedFallback.content_locale).toBe('en');
        expect(servedFallback.itinerary_locale).toBe('en');
        expect(servedFallback.translation_status).toBe('pending');
        expect(servedFallback.translation_warning).toBe('partial_translation');
        expect(servedFallback.guide_languages).toEqual(['de', 'en', 'es']);

        const url = `http://127.0.0.1:${nextPort}/${scenario.locale}/tours/${slug}`;
        await page.goto(url);
        const fallbackHeading = page.getByRole('heading', { level: 1 });
        await expect(fallbackHeading).toHaveText(source.title);
        // Spec 019 US2: full English fallback discloses general content and
        // the nonempty itinerary independently; the URL stays stable. Actual
        // fallback text declares lang="en" on the requested-locale page.
        expect(page.url()).toBe(url);
        await expect(page.getByText(scenario.notice)).toBeVisible();
        await expect(page.getByText(scenario.itineraryNotice)).toBeVisible();
        await expect(fallbackHeading).toHaveAttribute('lang', 'en');
        await expect(page.locator('#itinerary')).toContainText('English arrival');
        await expect(page.locator('#itinerary h3 span').first()).toHaveAttribute('lang', 'en');
        await expect(page.getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();

        current = { ...source,
          title: scenario.title,
          description: `Localized content for ${scenario.locale}`,
          content_locale: scenario.locale,
          itinerary_locale: scenario.locale,
          itinerary: [{ day: 1, title: scenario.day, stops: [{ title: scenario.day }] }],
          translation_status: 'ready',
          translation_warning: undefined,
          seo: { ...source.seo, meta_title: `${scenario.title} | Bookly`, meta_description: `Localized content for ${scenario.locale}` },
        };
        const servedReady = await readServedDetail(mockPort, slug, scenario.locale);
        expect(servedReady.content_locale).toBe(scenario.locale);
        expect(servedReady.itinerary_locale).toBe(scenario.locale);
        expect(servedReady.translation_status).toBe('ready');
        expect(servedReady.translation_warning).toBeUndefined();

        await page.reload();
        expect(page.url()).toBe(url);
        const readyHeading = page.getByRole('heading', { level: 1 });
        await expect(readyHeading).toHaveText(scenario.title);
        await expect(page.getByText(scenario.notice)).toHaveCount(0);
        await expect(page.getByText(scenario.itineraryNotice)).toHaveCount(0);
        await expect(readyHeading).toHaveAttribute('lang', scenario.locale);
        await expect(page.locator('#itinerary')).toContainText(scenario.day);
        await expect(page.locator('#itinerary')).not.toContainText('English arrival');
        await expect(page.locator('#itinerary h3 span').first()).toHaveAttribute('lang', scenario.locale);
        await expect(page.getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
        const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
        expect(schema.inLanguage).toBe(scenario.locale);
      }
    } finally {
      await harness.stop();
    }
  });
  // Root correction R5: independent general/itinerary transition in both
  // ES and IT, with served-response assertions, actual lang attributes,
  // stable URLs, localized controls and stable guide codes.
  test('ES/IT SSR keeps current general content while the itinerary falls back, then shows ready on reload', async ({ page, request }) => {
    test.setTimeout(180000);
    const slug = 'hidden-gems-rome-walking-tour';
    const fixtureResponse = await request.get(`/api/public/tours/${slug}?locale=en`);
    expect(fixtureResponse.ok()).toBeTruthy();
    const fixture = (await fixtureResponse.json()).data as TourDetail;
    let current = structuredClone(fixture);
    const harness = await startSsrHarness(slug, () => current);
    const { mockPort, nextPort } = harness;

    try {
      for (const scenario of [
        { locale: 'es' as const, title: 'Título español actual', description: 'Descripción española actual.', day: 'Llegada española', contentNotice: 'El contenido se muestra en inglés', itineraryNotice: 'El itinerario se muestra en inglés', guide: 'Guía en vivo' },
        { locale: 'it' as const, title: 'Titolo italiano attuale', description: 'Descrizione italiana attuale.', day: 'Arrivo italiano', contentNotice: 'Il contenuto è mostrato in inglese', itineraryNotice: "L'itinerario è mostrato in inglese", guide: 'Guida dal vivo' },
      ]) {
        // Phase 1: current requested general content, unavailable requested
        // itinerary -> independent English itinerary fallback with its own
        // notice; general content stays requested with no content notice.
        const source = structuredClone(fixture);
        source.title = scenario.title;
        source.description = scenario.description;
        source.content_locale = scenario.locale;
        source.itinerary_locale = 'en';
        source.itinerary = [{ day: 1, title: 'English arrival', stops: [{ title: 'Meet the guide' }] }];
        source.guide_languages = ['de', 'en', 'es'];
        source.languages = source.guide_languages;
        source.translation_status = 'ready';
        source.translation_warning = 'partial_translation';
        source.images = [];
        source.operator = null;
        source.related_tours = [];
        source.reviews = { average_rating: 0, count: 0, distribution: {} };
        source.rating = { average: 0, count: 0 };
        source.availability = { ...source.availability, available_dates: [], next_available_date: null, is_unavailable: true };
        source.seo = { ...source.seo, meta_title: `${source.title} | Bookly`, meta_description: source.description };
        current = source;

        const servedFallback = await readServedDetail(mockPort, slug, scenario.locale);
        expect(servedFallback.content_locale).toBe(scenario.locale);
        expect(servedFallback.itinerary_locale).toBe('en');
        expect(servedFallback.translation_status).toBe('ready');
        expect(servedFallback.translation_warning).toBe('partial_translation');
        expect(servedFallback.guide_languages).toEqual(['de', 'en', 'es']);

        const url = `http://127.0.0.1:${nextPort}/${scenario.locale}/tours/${slug}`;
        await page.goto(url);
        const fallbackHeading = page.getByRole('heading', { level: 1 });
        await expect(fallbackHeading).toHaveText(source.title);
        expect(page.url()).toBe(url);
        await expect(page.getByText(scenario.itineraryNotice)).toBeVisible();
        await expect(page.getByText(scenario.contentNotice)).toHaveCount(0);
        await expect(fallbackHeading).toHaveAttribute('lang', scenario.locale);
        await expect(page.locator('#itinerary')).toContainText('English arrival');
        await expect(page.locator('#itinerary h3 span').first()).toHaveAttribute('lang', 'en');
        await expect(page.getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();

        // Phase 2: the requested itinerary becomes current. The same URL
        // reload (fresh SSR read) replaces the fallback without re-entry or
        // a route change; spoken guide codes stay stable.
        current = { ...source,
          itinerary_locale: scenario.locale,
          itinerary: [{ day: 1, title: scenario.day, stops: [{ title: scenario.day }] }],
          translation_warning: undefined,
          seo: { ...source.seo },
        };
        const servedReady = await readServedDetail(mockPort, slug, scenario.locale);
        expect(servedReady.content_locale).toBe(scenario.locale);
        expect(servedReady.itinerary_locale).toBe(scenario.locale);
        expect(servedReady.translation_warning).toBeUndefined();

        await page.reload();
        expect(page.url()).toBe(url);
        const readyHeading = page.getByRole('heading', { level: 1 });
        await expect(readyHeading).toHaveText(source.title);
        await expect(readyHeading).toHaveAttribute('lang', scenario.locale);
        await expect(page.locator('#itinerary')).toContainText(scenario.day);
        await expect(page.locator('#itinerary')).not.toContainText('English arrival');
        await expect(page.locator('#itinerary h3 span').first()).toHaveAttribute('lang', scenario.locale);
        await expect(page.getByText(scenario.itineraryNotice)).toHaveCount(0);
        await expect(page.getByText(scenario.contentNotice)).toHaveCount(0);
        await expect(page.getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
        const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
        expect(schema.inLanguage).toBe(scenario.locale);
      }
    } finally {
      await harness.stop();
    }
  });
  test('tour detail page loads with all sections', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    // Page should load
    await expect(page.locator('main')).toBeVisible();

    // Title should be present
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('image gallery is visible', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const gallery = page.locator('.aspect-\\[16\\/10\\]').first();
    await expect(gallery).toBeVisible({ timeout: 10000 });
  });

  test('image gallery navigation buttons work when multiple images', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const nextBtn = page.getByRole('button', { name: 'Next image' });
    const prevBtn = page.getByRole('button', { name: 'Previous image' });

    if (await nextBtn.isVisible()) {
      await nextBtn.click();
      await expect(page.locator('[aria-current="true"]').first()).toBeVisible();
    }

    if (await prevBtn.isVisible()) {
      await prevBtn.click();
    }
  });

  test('lightbox opens from a keyboard-focusable trigger and restores focus on Escape', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const trigger = page.getByRole('button', { name: 'Open image gallery' });
    await expect(trigger).toBeVisible();
    await trigger.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('dialog', { name: 'Image lightbox' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Close lightbox' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog', { name: 'Image lightbox' })).not.toBeVisible();
    await expect(trigger).toBeFocused();
  });

  test('section navigation links only to rendered sections', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');
    const nav = page.getByRole('navigation', { name: 'Tour sections' });
    await expect(nav).toBeVisible();
    const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
    expect(hrefs.length).toBeGreaterThan(0);
    for (const href of hrefs) {
      expect(href).toMatch(/^#[a-z-]+$/);
      await expect(page.locator(href!)).toHaveCount(1);
    }
  });

  test('availability calendar shows available dates', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const calendar = page.getByText('Select a Date');
    await expect(calendar).toBeVisible({ timeout: 10000 });
  });

  test('booking CTA is visible', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const bookNow = page.getByRole('button', { name: /Book Now/i });
    const bookNowLink = page.getByRole('link', { name: /Book Now/i });

    const hasCTA = (await bookNow.isVisible().catch(() => false))
      || (await bookNowLink.isVisible().catch(() => false));

    // May show "Currently Unavailable" instead
    const unavailable = page.getByText('Currently Unavailable');
    const hasUnavailable = await unavailable.isVisible().catch(() => false);

    expect(hasCTA || hasUnavailable).toBeTruthy();
  });

  test('participant selector increments and decrements', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const increaseBtn = page.getByRole('button', { name: 'Increase participants' });
    const decreaseBtn = page.getByRole('button', { name: 'Decrease participants' });

    if (await increaseBtn.isVisible()) {
      await increaseBtn.click();
      // Count should change - exact value depends on tour's group size
      await expect(page.getByText(/allowed/)).toBeVisible();
      await expect(decreaseBtn).toBeVisible();
    }
  });

  test('shows 404 page for non-existent tour', async ({ page }) => {
    await page.goto('/en/tours/this-tour-does-not-exist-xyz');

    // Next.js default not-found behavior or API error
    await expect(page.locator('main')).toBeVisible();
  });

  test('shows the reviews section only when the API reports genuine reviews', async ({ page, request }) => {
    const response = await request.get('/api/public/tours/hidden-gems-rome-walking-tour?locale=en');
    expect(response.ok()).toBeTruthy();
    const { data } = await response.json();
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const reviewsHeading = page.getByRole('heading', { name: /Reviews/i });
    const reviewsLink = page.getByRole('navigation', { name: 'Tour sections' }).getByRole('link', { name: 'Reviews' });
    if (data.reviews.count > 0) {
      await expect(reviewsHeading).toBeVisible();
      await expect(reviewsLink).toBeVisible();
    } else {
      await expect(reviewsHeading).toHaveCount(0);
      await expect(reviewsLink).toHaveCount(0);
    }
  });

  test('about section displays description', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const aboutHeading = page.getByRole('heading', { name: 'About This Tour' });
    await expect(aboutHeading).toBeVisible({ timeout: 10000 });
  });

  test('tour detail has proper SEO metadata', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    await expect(page).toHaveTitle(/Bookly/);

    // Check canonical link
    const canonical = page.locator('link[rel="canonical"]');
    if (await canonical.isVisible().catch(() => false)) {
      const href = await canonical.getAttribute('href');
      expect(href).toContain('/tours/');
    }
  });

  // ─── Responsive Viewport Tests (FR-032, T092) ───

  const viewports = [
    { name: 'mobile', width: 375, height: 667 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1280, height: 800 },
  ];

  for (const vp of viewports) {
    test(`tour detail page adapts to ${vp.name} viewport (${vp.width}px) without horizontal scroll`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await page.goto('/en/tours/hidden-gems-rome-walking-tour');

      await expect(page.locator('main')).toBeVisible({ timeout: 10000 });

      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
      expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
    });
  }

  test('tour detail content stacks vertically on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    await expect(page.locator('main')).toBeVisible({ timeout: 10000 });

    // Content should not overflow horizontally
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  });

  test('structured data matches the visible tour and does not invent reviews', async ({ page, request }) => {
    const slug = 'hidden-gems-rome-walking-tour';
    const response = await request.get(`/api/public/tours/${slug}?locale=en`);
    expect(response.ok()).toBeTruthy();
    const { data } = await response.json();
    await page.goto(`/en/tours/${slug}`);
    const script = page.locator('script[type="application/ld+json"]').first();
    const schema = JSON.parse(await script.textContent() ?? '{}');
    expect(schema['@type']).toBe('TouristTrip');
    expect(schema.name).toBe(await page.getByRole('heading', { level: 1 }).textContent());
    expect(schema.inLanguage).toBe('en');
    expect(schema.description).toContain('Colosseum main entrance');
    expect(schema.image).toEqual(expect.arrayContaining([expect.stringMatching(/^https?:\/\//)]));
    if (data.reviews.count > 0 && data.reviews.average_rating > 0) {
      expect(schema.aggregateRating.reviewCount).toBe(data.reviews.count);
      expect(schema.aggregateRating.ratingValue).toBe(data.reviews.average_rating.toFixed(1));
    } else {
      expect(schema.aggregateRating).toBeUndefined();
    }
  });

  test('mobile booking action is keyboard reachable and uses a real offered date', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');
    const action = page.getByRole('link', { name: 'Continue to booking' });
    await expect(action).toBeVisible();
    const href = await action.getAttribute('href');
    expect(href).toMatch(/^\/en\/booking\?tour=hidden-gems-rome-walking-tour&participants=1&date=\d{4}-\d{2}-\d{2}$/);
    await action.focus();
    await expect(action).toBeFocused();
  });

  for (const [locale, bookNow, selectDate] of [
    ['en', 'Book Now', 'Select a Date'],
    ['es', 'Reservar Ahora', 'Seleccionar Fecha'],
    ['it', 'Prenota Ora', 'Seleziona Data'],
  ]) {
    test(`${locale} detail keeps localized booking controls and a real offered date`, async ({ page, request }) => {
      const slug = 'hidden-gems-rome-walking-tour';
      const response = await request.get(`/api/public/tours/${slug}?locale=${locale}`);
      expect(response.ok()).toBeTruthy();
      const { data } = await response.json();
      await page.goto(`/${locale}/tours/${slug}`);
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(data.title);
      await expect(page.getByRole('heading', { name: selectDate })).toBeVisible();
      const action = page.getByRole('link', { name: bookNow }).first();
      await expect(action).toBeVisible();
      const href = new URL((await action.getAttribute('href'))!, 'http://bookly.test');
      expect(href.searchParams.get('date')).toBe(data.availability.next_available_date);
      expect(data.availability.available_dates).toContain(href.searchParams.get('date'));
    });
  }
});
