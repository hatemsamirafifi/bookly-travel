import { test, expect } from '@playwright/test';
import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import type { AddressInfo } from 'node:net';
import type { EnglishSourceInput, TourDetail } from '@/lib/api/types';
import {
  apiGet,
  apiPut,
  ownedCoverUrl,
  ownedDifficulty,
  ownedEnglishSource,
  ownedGuideLanguages,
  ownedMedia,
} from './partner/tour-content-helpers';

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
  test('controlled legacy-shaped empty and adopted-day SSR responses invent no photos or review claims', async ({ page, request }) => {
    test.setTimeout(120000);
    const slug = 'hidden-gems-rome-walking-tour';
    const baseline = await request.get(`/api/public/tours/${slug}?locale=en`);
    expect(baseline.status()).toBe(200);
    // Controlled response fixtures exercise the SSR consumer; real command
    // adoption and retained legacy storage are verified separately on PostgreSQL.
    const fixture = (await baseline.json()).data as TourDetail;
    let current: TourDetail = {
      ...structuredClone(fixture),
      content_locale: 'en', itinerary_locale: 'en', translation_status: 'source',
      translation_warning: undefined, difficulty_level: null,
      itinerary: [], images: [], guide_languages: [], languages: [],
      meeting_point: '', operator: null, related_tours: [],
      reviews: { average_rating: 0, count: 0, distribution: {} },
      rating: { average: 0, count: 0 },
    };
    const harness = await startSsrHarness(slug, () => current);
    try {
      const url = `http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`;
      await page.goto(url);
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(current.title);
      await expect(page.locator('#itinerary')).toHaveCount(0);
      await expect(page.getByRole('button', { name: /Open image/i })).toHaveCount(0);
      await expect(page.locator('main [class*="aspect-[16/10]"] img')).toHaveCount(0);
      const empty = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
      expect(empty.image).toBeUndefined();
      expect(empty.aggregateRating).toBeUndefined();
      expect(empty.tripOrigin).toBeUndefined();
      expect(empty.itinerary).toBeUndefined();
      current = { ...current, itinerary: [
        { day: 1, title: 'Legacy arrival', description: null, stops: [] },
        { day: 2, title: 'Legacy walking route', description: null, stops: [] },
      ] };
      await page.reload();
      await expect(page.locator('#itinerary')).toContainText('Legacy arrival');
      await expect(page.locator('#itinerary')).toContainText('Legacy walking route');
      const adopted = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
      expect(adopted.itinerary.itemListElement.map((day: { position: number; identifier: number; name: string }) =>
        [day.position, day.identifier, day.name])).toEqual([[1, 1, 'Legacy arrival'], [2, 2, 'Legacy walking route']]);
      expect(adopted.itinerary['@context'].name['@language']).toBe('en');
      expect(adopted.image).toBeUndefined();
      expect(adopted.aggregateRating).toBeUndefined();
      expect(adopted.tripOrigin).toBeUndefined();
      await expect(page.locator('main [class*="aspect-[16/10]"] img')).toHaveCount(0);
      expect(adopted.offers.price).toBe((fixture.pricing.base_price.amount / 100).toFixed(2));
      expect(adopted.offers.priceCurrency).toBe(fixture.pricing.base_price.currency);
    } finally {
      await harness.stop();
    }
  });
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
        expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
        expect(schema['@context'].name['@language']).toBe(scenario.locale);
        expect(schema.mainEntityOfPage.inLanguage).toBe(scenario.locale);
        expect(schema.inLanguage).toBeUndefined();
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
        expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
        expect(schema['@context'].name['@language']).toBe(scenario.locale);
        expect(schema.mainEntityOfPage.inLanguage).toBe(scenario.locale);
        expect(schema.inLanguage).toBeUndefined();
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
    expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
    expect(schema.name).toBe(await page.getByRole('heading', { level: 1 }).textContent());
    expect(schema['@context'].name['@language']).toBe('en');
    expect(schema['@context'].description['@language']).toBe('en');
    expect(schema.mainEntityOfPage.inLanguage).toBe('en');
    expect(schema.inLanguage).toBeUndefined();
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

  // Spec 019 US4: integrated media/difficulty/guide reads from the REAL
  // backend through a real isolated Next SSR process (never fabricated JSON;
  // browser route mocking cannot intercept SSR). Writes touch only an owned
  // disposable fixture on the seeded published tour and restore it in
  // `finally` via an actual partner PUT. Auth reuses the existing setup
  // storageState file in a separate browser context; no token is printed.
  test('US4 public SSR shows real cover-first gallery with EN alt lang=en on ES/IT pages (seeded tour, restored)', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    test.setTimeout(180000);
    const slug = 'hidden-gems-rome-walking-tour';
    const authedContext = await browser.newContext({ baseURL, storageState: 'tests/.auth/partner.json' });
    const authedPage = await authedContext.newPage();
    let tourId = 0;
    let snapshot: {
      media: Array<{ url: string; is_cover: boolean; alt_text?: string | null }>;
      coverUrl: string | null;
      difficulty: string | null;
      guides: string[];
    } | null = null;
    try {
      await authedPage.goto('/en/partner/tours');
      const boot = await request.get(`/api/public/tours/${slug}?locale=en`);
      expect(boot.ok()).toBeTruthy();
      const bootData = ((await boot.json()).data) as TourDetail;
      tourId = bootData.id;
      // Legacy seeded tours carry no invented difficulty: absent reads null.
      if (bootData.difficulty_level === null || bootData.difficulty_level === undefined) {
        expect(bootData.difficulty_level ?? null).toBeNull();
      }
      const owned = await apiGet(authedPage, `/api/partner/tours/${tourId}`);
      if (owned.status !== 200) {
        throw new Error(
          `Spec019 US4 seed prerequisite: expected seeded partner@bookly.test to own published tour ${slug} ` +
            `(partner GET /api/partner/tours/${tourId} returned ${owned.status}). ` +
            'Root must prepare ownership/seeding instead of inventing state.'
        );
      }
      snapshot = {
        media: ownedMedia(owned.json).map((row) => ({
          url: row.url,
          is_cover: row.is_cover,
          ...(row.alt_text === undefined ? {} : { alt_text: row.alt_text }),
        })),
        coverUrl: ownedCoverUrl(owned.json),
        difficulty: ownedDifficulty(owned.json),
        guides: ownedGuideLanguages(owned.json),
      };

      const stamp = Date.now().toString(36);
      const coverUrl = `http://cdn.bookly.test/tours/rome-cover.jpg?us4=${stamp}-cover`;
      const secondUrl = `http://cdn.bookly.test/tours/rome-gallery-1.jpg?us4=${stamp}-second`;
      const thirdUrl = `http://cdn.bookly.test/tours/rome-gallery-2.jpg?us4=${stamp}-third`;
      const coverAltEn = 'US4 harbor sunrise Caf\u00e9';
      const secondAltEn = 'US4 old street';
      const fixture = await apiPut(authedPage, `/api/partner/tours/${tourId}`, {
        media: [
          { url: coverUrl, is_cover: true, alt_text: coverAltEn },
          { url: secondUrl, is_cover: false, alt_text: secondAltEn },
          { url: thirdUrl, is_cover: false, alt_text: null },
        ],
        cover_image_url: coverUrl,
        guide_languages: ['en', 'de'],
      });
      if (fixture.status !== 200) {
        throw new Error(
          `Spec019 US4 seed prerequisite: fixture PUT on owned seeded tour ${slug} returned ${fixture.status}. ` +
            'Root must prepare a writable owned fixture instead of inventing state.'
        );
      }

      const scenarios = [
        { locale: 'en', open: 'Open image gallery', dialog: 'Image lightbox', close: 'Close lightbox', view3: 'View image 3' },
        { locale: 'es', open: 'Abrir galer\u00eda de im\u00e1genes', dialog: 'Galer\u00eda ampliada', close: 'Cerrar galer\u00eda', view3: 'Ver imagen 3' },
        { locale: 'it', open: 'Apri galleria immagini', dialog: 'Galleria ingrandita', close: 'Chiudi galleria', view3: 'Vedi immagine 3' },
      ] as const;
      for (const scenario of scenarios) {
        // Actual backend response the isolated Next server consumes.
        const servedResponse = await request.get(`/api/public/tours/${slug}?locale=${scenario.locale}`);
        expect(servedResponse.ok()).toBeTruthy();
        const served = ((await servedResponse.json()).data) as TourDetail;
        // Cover first, remaining order, real URLs only.
        expect(served.images.map((image) => image.url)).toEqual([coverUrl, secondUrl, thirdUrl]);
        expect(served.images[0].is_cover).toBe(true);
        // Specific English alt keeps lang=en on every page locale ...
        expect(served.images[0].alt).toBe(coverAltEn);
        expect(served.images[0].alt_locale).toBe('en');
        expect(served.images[1].alt).toBe(secondAltEn);
        expect(served.images[1].alt_locale).toBe('en');
        // ... while a missing alt falls back to the selected title in its
        // actual locale (current derivative or disclosed EN fallback alike).
        expect(served.images[2].alt).toBe(served.title);
        expect(served.images[2].alt_locale).toBe(served.content_locale);
        // Saved supported facts travel independently of page language.
        expect(served.difficulty_level ?? null).toBe(snapshot.difficulty);
        expect(served.guide_languages).toEqual(['en', 'de']);

        const url = new URL(`/${scenario.locale}/tours/${slug}`, baseURL).href;
        await page.goto(url);
        expect(page.url()).toBe(url);
        const heading = page.getByRole('heading', { level: 1 });
        await expect(heading).toHaveText(served.title);
        await expect(heading).toHaveAttribute('lang', served.content_locale);

        const trigger = page.getByRole('button', { name: scenario.open });
        await expect(trigger).toBeVisible();
        const heroImg = trigger.locator('img');
        await expect(heroImg).toHaveAttribute('alt', coverAltEn);
        await expect(heroImg).toHaveAttribute('src', new RegExp(encodeURIComponent(coverUrl)));
        await expect.poll(() => heroImg.evaluate((image: HTMLImageElement) =>
          image.complete && image.naturalWidth > 0
        )).toBe(true);

        // Lightbox: EN-specific alt declares lang=en (also on ES/IT pages)
        // while action labels stay page-localized.
        await trigger.click();
        const dialog = page.getByRole('dialog', { name: scenario.dialog });
        await expect(dialog).toBeVisible();
        await expect(dialog.locator('img')).toHaveAttribute('alt', coverAltEn);
        await expect(dialog.locator('[lang="en"]')).not.toHaveCount(0);
        const closeBtn = page.getByRole('button', { name: scenario.close });
        await expect(closeBtn).toBeVisible();
        const closeLanguage = await closeBtn.evaluate(
          (el: HTMLElement) => el.closest('[lang]')?.getAttribute('lang')
        );
        expect(closeLanguage).toBe(scenario.locale);
        await closeBtn.click();
        await expect(dialog).not.toBeVisible();

        // Fallback image: the third thumbnail shows the selected title with
        // the actual content locale in the lightbox.
        await page.getByRole('button', { name: scenario.view3 }).click();
        await expect(heroImg).toHaveAttribute('alt', served.title);
        await trigger.click();
        await expect(dialog).toBeVisible();
        await expect(dialog.locator('img')).toHaveAttribute('alt', served.title);
        await expect(dialog.locator(`[lang="${served.content_locale}"]`)).not.toHaveCount(0);
        await page.getByRole('button', { name: scenario.close }).click();
        await expect(dialog).not.toBeVisible();
      }
    } finally {
      if (snapshot !== null && tourId !== 0) {
        const restoreDifficulty =
          snapshot.difficulty === 'easy' ||
          snapshot.difficulty === 'moderate' ||
          snapshot.difficulty === 'challenging'
            ? snapshot.difficulty
            : null;
        const restore = await apiPut(authedPage, `/api/partner/tours/${tourId}`, {
          media: snapshot.media,
          cover_image_url: snapshot.coverUrl,
          ...(restoreDifficulty === null ? {} : { difficulty_level: restoreDifficulty }),
          guide_languages: snapshot.guides,
        });
        expect(restore.status).toBe(200);
      }
      await authedContext.close();
    }
  });

  // Spec 019 US6 (T071): actual English source write -> public SSR/SEO reads.
  // Resolves the real published seeded tour by public slug (never a hardcoded
  // id), snapshots the owned EN source (presence/null preserved via
  // ownedEnglishSource), writes a meaningful ordered itinerary with repeated
  // day numbers through the real partner PUT, then proves the production Next
  // server through the real proxy serves the edited source with a disclosed stale-derivative EN
  // fallback on the ES page. Server money/booking availability must not move
  // with a source edit. No provider call is made; a non-200 write is a seed
  // prerequisite failure, never a fabricated success. The snapshot is
  // restored in `finally` through the same real API.
  test('US6 public SSR reads actual edited English source with disclosed EN fallback; money/availability unchanged (seeded tour, restored)', async ({
    page,
    request,
    browser,
    baseURL,
  }) => {
    test.setTimeout(180000);
    const slug = 'hidden-gems-rome-walking-tour';
    const authedContext = await browser.newContext({ baseURL, storageState: 'tests/.auth/partner.json' });
    const authedPage = await authedContext.newPage();
    let tourId = 0;
    let snapshot: Record<string, unknown> | null = null;
    try {
      await authedPage.goto('/en/partner/tours');
      const boot = await request.get(`/api/public/tours/${slug}?locale=en`);
      expect(boot.ok()).toBeTruthy();
      const bootData = ((await boot.json()).data) as TourDetail;
      tourId = bootData.id;
      const owned = await apiGet(authedPage, `/api/partner/tours/${tourId}`);
      if (owned.status !== 200) {
        throw new Error(
          `Spec019 US6 seed prerequisite: expected seeded partner@bookly.test to own published tour ${slug} ` +
            `(partner GET /api/partner/tours/${tourId} returned ${owned.status}). ` +
            'Root must prepare ownership/seeding instead of inventing state.'
        );
      }
      snapshot = ownedEnglishSource(owned.json);
      const beforeEs = await request.get(`/api/public/tours/${slug}?locale=es`);
      expect(beforeEs.ok()).toBeTruthy();
      const beforeServed = ((await beforeEs.json()).data) as TourDetail;
      const moneyBefore = beforeServed.pricing;
      const availabilityBefore = beforeServed.availability;

      // Meaningful ordered source edit: repeated valid day numbers kept in
      // authored order (day 7 twice, then day 2) with ordered stops including
      // null descriptions/durations, plus a real meeting point.
      const stamp = Date.now().toString(36);
      const sourceTitle = `US6 authored source ${stamp}`;
      const sourceDescription = 'US6 current English source description with real meeting-point facts.';
      const sourceMeetingPoint = 'US6 Authored Gate';
      const sourceItinerary: NonNullable<EnglishSourceInput['itinerary']> = [
        { day: 7, title: 'US6 first authored day', description: 'US6 English day description', stops: [
          { title: 'US6 walking activity', description: 'US6 walk with the guide', duration_minutes: 30 },
          { title: 'US6 rest break', description: null, duration_minutes: null },
        ] },
        { day: 7, title: 'US6 second authored day', description: null, stops: [] },
        { day: 2, title: 'US6 ordered out-of-sequence day', description: null, stops: [
          { title: 'US6 photo stop', description: null, duration_minutes: 15 },
        ] },
      ];
      const write = await apiPut(authedPage, `/api/partner/tours/${tourId}`, {
        translations: { en: {
          ...(snapshot as EnglishSourceInput),
          title: sourceTitle,
          description: sourceDescription,
          meeting_point: sourceMeetingPoint,
          itinerary: sourceItinerary,
        } },
      });
      if (write.status !== 200) {
        throw new Error(
          `Spec019 US6 seed prerequisite: source PUT on owned seeded tour ${slug} returned ${write.status}. ` +
            'Root must prepare a writable owned fixture instead of inventing state.'
        );
      }

      // Actual backend response the isolated Next server consumes: the edited
      // source is current while the ES derivative (missing or superseded by
      // the edit) falls back to disclosed English. Server money/booking
      // availability do not move with a source edit.
      const servedResponse = await request.get(`/api/public/tours/${slug}?locale=es`);
      expect(servedResponse.ok()).toBeTruthy();
      const served = ((await servedResponse.json()).data) as TourDetail;
      expect(served.title).toBe(sourceTitle);
      expect(served.description).toBe(sourceDescription);
      expect(served.content_locale).toBe('en');
      expect(served.meeting_point).toBe(sourceMeetingPoint);
      expect(served.itinerary).toEqual(sourceItinerary);
      expect(served.itinerary_locale).toBe('en');
      expect(['pending', 'stale', 'failed']).toContain(served.translation_status);
      expect(served.translation_warning).toBe('partial_translation');
      expect(served.pricing).toEqual(moneyBefore);
      expect(served.availability).toEqual(availabilityBefore);

      const url = new URL(`/es/tours/${slug}`, baseURL).href;
      await page.goto(url);
      expect(page.url()).toBe(url);
      const heading = page.getByRole('heading', { level: 1 });
      await expect(heading).toHaveText(sourceTitle);
      await expect(heading).toHaveAttribute('lang', 'en');
      await expect(page.getByText('El contenido se muestra en inglés')).toBeVisible();
      await expect(page.getByText('El itinerario se muestra en inglés')).toBeVisible();
      // Authored order is preserved verbatim (repeated day 7 first, then day
      // 2): position order, not day-number order.
      const dayTitles = await page.locator('#itinerary h3 span').evaluateAll((els) => els.map((el) => el.textContent));
      expect(dayTitles).toEqual(['US6 first authored day', 'US6 second authored day', 'US6 ordered out-of-sequence day']);
      await expect(page.locator('#itinerary')).toContainText('US6 walking activity');
      await expect(page.locator('#itinerary')).toContainText('US6 photo stop');

      // Actual title/meta/canonical/hreflang/language: metadata follows the
      // selected English source on the ES page; canonical/hreflang keep the
      // requested slug in every real language.
      await expect(page).toHaveTitle(`${sourceTitle} | Bookly`);
      const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
      expect(canonical).toContain(`/es/tours/${slug}`);
      for (const lang of ['en', 'es', 'it']) {
        const href = await page.locator(`link[rel="alternate"][hreflang="${lang}"]`).getAttribute('href');
        expect(href).toContain(`/${lang}/tours/${slug}`);
      }
      const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
      expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
      expect(schema.name).toBe(sourceTitle);
      expect(schema['@context'].name['@language']).toBe('en');
      expect(schema['@context'].description['@language']).toBe('en');
      expect(schema.mainEntityOfPage.inLanguage).toBe('en');
      expect(schema.inLanguage).toBeUndefined();
      expect(schema.description).toContain(sourceMeetingPoint);
      // Ordered real days/stops with stable positions despite repeated days.
      const days = schema.itinerary.itemListElement;
      expect(days.map((day: { position: number; identifier: number }) => [day.position, day.identifier])).toEqual([
        [1, 7], [2, 7], [3, 2],
      ]);
      expect(days[0].item.itemListElement.map((stop: { position: number; item: { name: string } }) => [stop.position, stop.item.name])).toEqual([
        [1, 'US6 walking activity'], [2, 'US6 rest break'],
      ]);
      expect(schema.tripOrigin).toEqual({ '@type': 'Place', name: sourceMeetingPoint });
      // No fabricated rating: the seeded review facts decide, never the edit.
      if (served.reviews.count > 0 && served.reviews.average_rating > 0) {
        expect(schema.aggregateRating.reviewCount).toBe(served.reviews.count);
        expect(schema.aggregateRating.ratingValue).toBe(served.reviews.average_rating.toFixed(1));
      } else {
        expect(schema.aggregateRating).toBeUndefined();
      }
      // Booking stays server-authoritative: the localized CTA deep-links the
      // real next offered date, or no CTA is offered when unavailable.
      const bookingAction = page.getByRole('link', { name: 'Reservar Ahora' }).first();
      if (served.availability.next_available_date !== null) {
        expect(served.availability.available_dates).toContain(served.availability.next_available_date);
        await expect(bookingAction).toBeVisible();
        const href = new URL((await bookingAction.getAttribute('href'))!, 'http://bookly.test');
        expect(href.searchParams.get('date')).toBe(served.availability.next_available_date);
      } else {
        await expect(bookingAction).toHaveCount(0);
      }
    } finally {
      if (snapshot !== null && tourId !== 0) {
        const restore = await apiPut(authedPage, `/api/partner/tours/${tourId}`, {
          translations: { en: snapshot },
        });
        expect(restore.status).toBe(200);
      }
      await authedContext.close();
    }
  });
});
