import { test, expect, type Page } from '@playwright/test';
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
import { fetchServedResponse, readServedDetail, startSsrHarness, enableNativeMediaProxy } from './helpers/tour-detail-ssr';
import type { SsrReading } from './helpers/tour-detail-ssr';
import { formatCurrency } from '@/lib/utils';
import {
  createFullTourDetail,
  createSparseTourDetail,
  createUnavailableTourDetail,
} from '../fixtures/tour-detail';

test.beforeEach(async ({ page, baseURL }) => {
  await page.context().addCookies([
    { name: 'bookly_cookie_consent', value: 'true', url: baseURL! },
    { name: 'bookly_cookie_consent', value: 'true', url: 'http://127.0.0.1' },
  ]);
  await enableNativeMediaProxy(page.context());
});

test.afterEach(async ({ context }) => {
  await context.unrouteAll({ behavior: 'wait' });
});

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
      // A streamed replacement must settle to one actual target before text
      // assertions; a persistent duplicate ID remains a failure.
      await expect(page.locator('#itinerary')).toHaveCount(1);
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
        await expect(page.locator('main#main-content').getByText(scenario.notice)).toBeVisible();
        await expect(page.locator('main#main-content').getByText(scenario.itineraryNotice)).toBeVisible();
        await expect(fallbackHeading).toHaveAttribute('lang', 'en');
        await expect(page.locator('main#main-content #itinerary')).toContainText('English arrival');
        await expect(page.locator('main#main-content #itinerary h3 span').first()).toHaveAttribute('lang', 'en');
        await expect(page.locator('main#main-content').getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();

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
        await expect(page.locator('main#main-content').getByText(scenario.notice)).toHaveCount(0);
        await expect(page.locator('main#main-content').getByText(scenario.itineraryNotice)).toHaveCount(0);
        await expect(readyHeading).toHaveAttribute('lang', scenario.locale);
        await expect(page.locator('main#main-content #itinerary')).toContainText(scenario.day);
        await expect(page.locator('main#main-content #itinerary')).not.toContainText('English arrival');
        await expect(page.locator('main#main-content #itinerary h3 span').first()).toHaveAttribute('lang', scenario.locale);
        await expect(page.locator('main#main-content').getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
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
        await expect(page.locator('main#main-content').getByText(scenario.itineraryNotice)).toBeVisible();
        await expect(page.locator('main#main-content').getByText(scenario.contentNotice)).toHaveCount(0);
        await expect(fallbackHeading).toHaveAttribute('lang', scenario.locale);
        await expect(page.locator('main#main-content #itinerary')).toContainText('English arrival');
        await expect(page.locator('main#main-content #itinerary h3 span').first()).toHaveAttribute('lang', 'en');
        await expect(page.locator('main#main-content').getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();

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
        await expect(page.locator('main#main-content #itinerary')).toContainText(scenario.day);
        await expect(page.locator('main#main-content #itinerary')).not.toContainText('English arrival');
        await expect(page.locator('main#main-content #itinerary h3 span').first()).toHaveAttribute('lang', scenario.locale);
        await expect(page.locator('main#main-content').getByText(scenario.itineraryNotice)).toHaveCount(0);
        await expect(page.locator('main#main-content').getByText(scenario.contentNotice)).toHaveCount(0);
        await expect(page.locator('main#main-content').getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
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

  test('image gallery rail controls move the counter with no thumbnail navigation', async ({ page }) => {
    // Mobile rail: the counter and rail arrows are the <1024px controls.
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    // Canonical gallery: one photo DOM (scroll-snap rail below 1024px,
    // mosaic at >=1024px); thumbnails no longer exist.
    const gallery = page.getByTestId('tour-gallery');
    await expect(gallery).toBeVisible({ timeout: 10000 });
    await expect(gallery.getByRole('list', { name: /thumbnail/i })).toHaveCount(0);
    const counter = gallery.getByText(/^\d+ of \d+$/);
    await expect(counter).toBeVisible();
    const initial = await counter.textContent();
    const total = Number(initial?.match(/of (\d+)$/)?.[1] ?? '0');
    expect(total).toBeGreaterThan(0);
    if (total > 1) {
      await gallery.getByRole('button', { name: 'Next image' }).click();
      await expect(counter).toHaveText(`2 of ${total}`);
      await gallery.getByRole('button', { name: 'Previous image' }).click();
      await expect(counter).toHaveText(`1 of ${total}`);
    } else {
      await expect(gallery.getByRole('button', { name: 'Next image' })).toHaveCount(0);
    }
  });

  test('lightbox opens from a keyboard-focusable tile and restores focus on Escape', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const trigger = page.getByRole('button', { name: 'View image 1' });
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

    if ((page.viewportSize()?.width ?? 0) < 1024) {
      await page.getByTestId('mobile-booking-bar').getByRole('button').click();
    }
    const calendar = page.getByRole('combobox', { name: 'Select a Date', exact: true });
    await expect(calendar).toBeVisible({ timeout: 10000 });
  });

  test('booking CTA is visible', async ({ page, request }) => {
    const response = await request.get('/api/public/tours/hidden-gems-rome-walking-tour?locale=en');
    expect(response.ok()).toBeTruthy();
    const { data } = await response.json();
    expect(data.availability.is_unavailable).toBe(false);
    expect(data.availability.available_dates).toContain(data.availability.next_available_date);
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(data.title);
    const action = (page.viewportSize()?.width ?? 0) < 1024
      ? page.getByTestId('mobile-booking-bar').getByRole('link', { name: 'Continue to booking' })
      : page.getByTestId('tour-booking-card-slot').getByRole('link', { name: 'Book Now', exact: true });
    await expect(action).toBeVisible();
    const auth = new URL((await action.getAttribute('href'))!, 'http://bookly.test');
    expect(auth.pathname).toBe('/en/auth/login');
    const booking = new URL(auth.searchParams.get('returnUrl')!, 'http://bookly.test');
    expect(booking.pathname).toBe('/en/booking');
    expect(booking.searchParams.get('date')).toBe(data.availability.next_available_date);
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

    const reviewsHeading = page.getByRole('heading', { level: 2, name: 'Reviews', exact: true });
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
    const href = new URL((await action.getAttribute('href'))!, 'http://bookly.test');
    expect(href.origin).toBe('http://bookly.test');
    expect(href.pathname).toBe('/en/auth/login');
    expect(href.searchParams.get('returnUrl')).toMatch(/^\/en\/booking\?tour=hidden-gems-rome-walking-tour&participants=1&date=\d{4}-\d{2}-\d{2}$/);
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
      if ((page.viewportSize()?.width ?? 0) < 1024) {
        await page.getByTestId('mobile-booking-bar').getByRole('button').click();
      }
      const dateSelector = page.getByRole('combobox', { name: selectDate, exact: true });
      await expect(dateSelector).toBeVisible();
      await expect(dateSelector).toHaveValue(data.availability.next_available_date);
      const action = page.getByRole('link', { name: bookNow }).first();
      await expect(action).toBeVisible();
      const authHref = new URL((await action.getAttribute('href'))!, 'http://bookly.test');
      expect(authHref.origin).toBe('http://bookly.test');
      expect(authHref.pathname).toBe(`/${locale}/auth/login`);
      const href = new URL(authHref.searchParams.get('returnUrl')!, 'http://bookly.test');
      expect(href.origin).toBe('http://bookly.test');
      expect(href.pathname).toBe(`/${locale}/booking`);
      expect(href.searchParams.get('tour')).toBe(slug);
      expect(href.searchParams.get('participants')).toBe(String(data.group_size.min));
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
        { locale: 'en', view1: 'View image 1', dialog: 'Image lightbox', close: 'Close lightbox', view3: 'View image 3', counter3: '3 of 3' },
        { locale: 'es', view1: 'Ver imagen 1', dialog: 'Galer\u00eda ampliada', close: 'Cerrar galer\u00eda', view3: 'Ver imagen 3', counter3: '3 de 3' },
        { locale: 'it', view1: 'Vedi immagine 1', dialog: 'Galleria ingrandita', close: 'Chiudi galleria', view3: 'Vedi immagine 3', counter3: '3 di 3' },
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

        const trigger = page.getByRole('button', { name: scenario.view1 });
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

        // Fallback image: the third tile opens the viewer at its own
        // original index showing the selected title with the actual
        // content locale.
        await page.getByRole('button', { name: scenario.view3 }).click();
        await expect(dialog).toBeVisible();
        await expect(dialog.locator('img')).toHaveAttribute('alt', served.title);
        await expect(dialog.locator(`[lang="${served.content_locale}"]`)).not.toHaveCount(0);
        await expect(dialog.getByText(scenario.counter3)).toBeVisible();
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
      const bookingAction = (page.viewportSize()?.width ?? 0) < 1024
        ? page.getByTestId('mobile-booking-bar').getByRole('link', { name: 'Continuar con la reserva' })
        : page.getByRole('link', { name: 'Reservar Ahora', exact: true });
      if (served.availability.next_available_date !== null) {
        expect(served.availability.available_dates).toContain(served.availability.next_available_date);
        await expect(bookingAction).toBeVisible();
        const actionHref = new URL((await bookingAction.getAttribute('href'))!, 'http://bookly.test');
        const href = new URL(actionHref.searchParams.get('returnUrl') ?? actionHref.href, 'http://bookly.test');
        expect(href.pathname).toBe('/es/booking');
        expect(href.searchParams.get('tour')).toBe(slug);
        expect(href.searchParams.get('participants')).toBe(String(served.group_size.min));
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

  // Spec 020 US1: ordered readable hierarchy through the real built SSR
  // server. Controlled complete/sparse responses prove prescribed order,
  // usable disclosure, important-information retention and no orphan nav
  // entries without touching shared tour data.
  for (const width of [390, 1440]) {
    test(`US1 SSR complete tour at ${width}px keeps prescribed order with disclosure and no orphan nav entries`, async ({ page }) => {
      test.setTimeout(180000);
      await page.setViewportSize({ width, height: 900 });
      const slug = 'hidden-gems-rome-walking-tour';
      const complete = createFullTourDetail(new Date('2026-10-14T00:00:00.000Z'));
      complete.description = `${'Tuscan sun. '.repeat(80)}Final reachable sentence.`;
      const harness = await startSsrHarness(slug, () => complete);
      try {
        await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
        const served = await readServedDetail(harness.mockPort, slug, 'en');
        expect(served.title).toBe(complete.title);
        expect(served.reviews.count).toBeGreaterThan(0);

        await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);

        // Header: breadcrumbs, title, location and genuine rating/review link.
        await expect(page.getByRole('navigation', { name: 'Breadcrumbs' })).toBeVisible();
        await expect(page.getByRole('heading', { level: 1 })).toHaveText(complete.title);
        await expect(page.getByText('Rome, Italy').first()).toBeVisible();
        const reviewLinks = page.locator('a[href="#reviews"]');
        await expect(reviewLinks).toHaveCount(2);

        // Prescribed section order in the served document.
        const ids = ['overview', 'facts', 'highlights', 'itinerary', 'included', 'excluded', 'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related'];
        const ordered = await page.evaluate((wanted: string[]) => {
          const elements = wanted.map((id) => document.getElementById(id));
          if (elements.some((element) => element === null)) return false;
          return elements.every((element, index) =>
            index === 0 || elements[index - 1]!.compareDocumentPosition(element!) === 4,
          );
        }, ids);
        expect(ordered).toBe(true);

        // Navigation targets exactly the rendered sections.
        const nav = page.getByRole('navigation', { name: 'Tour sections' });
        const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
        expect(hrefs).toEqual(ids.map((id) => `#${id}`));
        for (const href of hrefs) {
          await expect(page.locator(href!)).toHaveCount(1);
        }

        // Long overview: preview first, full text reachable through disclosure.
        await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
        const expand = page.getByRole('button', { name: 'Read more' });
        await expect(expand).toHaveAttribute('aria-expanded', 'false');
        expect(await expand.getAttribute('aria-controls')).toBeTruthy();
        await expand.click();
        await expect(page.getByText('Final reachable sentence.')).toBeVisible();
        const collapse = page.getByRole('button', { name: 'Read less' });
        await expect(collapse).toHaveAttribute('aria-expanded', 'true');
        await collapse.click();
        await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);

        // Practical information stays fully readable; genuine facts shown.
        await expect(page.locator('#important')).toContainText('Bring your passport');
        await expect(page.locator('#facts')).toContainText('Moderate');
        await expect(page.locator('#itinerary')).toContainText('Artisan quarter');
        await page.screenshot({ path: `../specs/020-tour-detail-experience/evidence/us1/visual/complete-en-${width}.png`, fullPage: true });
      } finally {
        await harness.stop();
      }
    });

    test(`US1 SSR sparse tour at ${width}px omits absent sections, entries and review claims`, async ({ page }) => {
      test.setTimeout(180000);
      await page.setViewportSize({ width, height: 900 });
      const slug = 'hidden-gems-rome-walking-tour';
      const sparse = createSparseTourDetail(new Date('2026-10-14T00:00:00.000Z'));
      const harness = await startSsrHarness(slug, () => sparse);
      try {
        await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
        const served = await readServedDetail(harness.mockPort, slug, 'en');
        expect(served.reviews.count).toBe(0);

        await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);

        await expect(page.getByRole('heading', { level: 1 })).toHaveText(sparse.title);
        await expect(page.locator('#overview')).toHaveCount(1);
        await expect(page.locator('#facts')).toHaveCount(1);
        await expect(page.locator('#overview')).toBeVisible();
        await expect(page.locator('#facts')).toBeVisible();
        for (const id of ['highlights', 'itinerary', 'included', 'excluded', 'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related']) {
          await expect(page.locator(`#${id}`)).toHaveCount(0);
        }
        const nav = page.getByRole('navigation', { name: 'Tour sections' });
        const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
        expect(hrefs).toEqual(['#overview', '#facts']);
        // Zero reviews: no scored rating and no review link anywhere.
        await expect(page.locator('a[href="#reviews"]')).toHaveCount(0);
        await expect(page.getByText(/out of 5/)).toHaveCount(0);
        // Short description stays fully readable with no disclosure.
        await expect(page.getByRole('button', { name: 'Read more' })).toHaveCount(0);
        await page.screenshot({ path: `../specs/020-tour-detail-experience/evidence/us1/visual/sparse-en-${width}.png`, fullPage: true });
      } finally {
        await harness.stop();
      }
    });
  }

  // Spec 020 US2: one shared booking selection with safe auth continuation.
  // Controlled SSR fixtures (60 offered dates from the real clock) prove card,
  // bar, resize, UTC boundary and login-handoff behavior without touching
  // shared tour data.
  test('US2 signed-out SSR shares a non-default date and count across card, bar and resize', async ({ page }) => {
    test.setTimeout(180000);
    const slug = 'hidden-gems-rome-walking-tour';
    const complete = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => complete);
    try {
      const served = await readServedDetail(harness.mockPort, slug, 'en');
      expect(served.availability.available_dates.length).toBe(60);
      const picked = served.availability.available_dates[31];
      const base = served.pricing.base_price.amount;

      await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
      await page.setViewportSize({ width: 1280, height: 900 });
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);

      const cardSelect = page.locator('main#main-content').getByLabel('Select a Date');
      await expect(cardSelect).toBeVisible();
      await cardSelect.selectOption(picked);
      await page.getByRole('button', { name: 'Increase participants' }).click();
      await page.getByRole('button', { name: 'Increase participants' }).click();

      // One display-only estimate for both surfaces: base x 3. The visible
      // card slot owns the desktop estimate (the hidden mobile bar carries
      // the same text); the bar summary is asserted explicitly after resize.
      const cardSlot = page.locator('main#main-content').getByTestId('tour-booking-card-slot');
      await expect(cardSlot.getByText(formatCurrency(base * 3, 'EUR', 'en'), { exact: false })).toBeVisible();
      // Signed out: continuation routes through safe login with the full query.
      const loginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
      const returnUrl = new URL(loginHref!, 'http://bookly.test').searchParams.get('returnUrl');
      expect(returnUrl).toBe(`/en/booking?tour=${slug}&participants=3&date=${picked}`);

      // Narrow viewport: the mobile bar shows the same selection and estimate.
      await page.setViewportSize({ width: 390, height: 844 });
      const summary = page.locator('main#main-content').getByTestId('mobile-bar-summary');
      await expect(summary).toBeVisible();
      await expect(summary).toContainText('3');

      // Back to desktop: the card kept the selection (no remount reset).
      await page.setViewportSize({ width: 1280, height: 900 });
      await expect(page.locator('main#main-content').getByTestId('tour-booking-card-slot').getByLabel('Select a Date')).toHaveValue(picked);
      const loginHrefAfter = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
      expect(new URL(loginHrefAfter!, 'http://bookly.test').searchParams.get('returnUrl')).toBe(
        `/en/booking?tour=${slug}&participants=3&date=${picked}`,
      );
    } finally {
      await harness.stop();
    }
  });

  // Spec 020 US2 rework: the mobile dialog edits the same shared selection
  // (default valid -> non-default index-31 date and count), closes, and the
  // summary/estimate/query survive resize in both directions with measured
  // 44px targets and a sticky card that spans the article.
  test('US2 mobile dialog edits shared selection with measured targets across resize', async ({ page }) => {
    test.setTimeout(180000);
    const slug = 'hidden-gems-rome-walking-tour';
    const complete = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => complete);
    try {
      const served = await readServedDetail(harness.mockPort, slug, 'en');
      const picked = served.availability.available_dates[31];
      const base = served.pricing.base_price.amount;
      await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);

      await page.setViewportSize({ width: 390, height: 844 });
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);

      // The bar is present for the initially valid default selection.
      const bar = page.locator('main#main-content').getByTestId('mobile-booking-bar');
      await expect(bar).toBeVisible();

      // Edit affordance meets the 44px minimum target while still valid.
      const edit = page.getByRole('button', { name: 'Edit booking selections' });
      await expect(edit).toBeVisible();
      expect((await edit.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(44);

      await edit.click();
      const dialog = page.getByRole('dialog');
      await expect(dialog).toBeVisible();
      await dialog.getByLabel('Select a Date').selectOption(picked);
      await dialog.getByRole('button', { name: 'Increase participants' }).click();
      // Dialog stepper targets meet the 44px minimum as well.
      const dialogIncrease = dialog.getByRole('button', { name: 'Increase participants' });
      expect((await dialogIncrease.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(44);
      await page.keyboard.press('Escape');
      await expect(dialog).not.toBeVisible();

      // Same visible formatted summary, estimate and query after close.
      const summary = page.locator('main#main-content').getByTestId('mobile-bar-summary');
      await expect(summary).toContainText('2');
      const barLoginHref = await bar.getByRole('link', { name: 'Continue to booking' }).getAttribute('href');
      const barReturn = new URL(barLoginHref!, 'http://bookly.test').searchParams.get('returnUrl');
      expect(barReturn).toBe(`/en/booking?tour=${slug}&participants=2&date=${picked}`);

      // Desktop: the card kept the same selection, estimate and query.
      await page.setViewportSize({ width: 1280, height: 900 });
      await expect(page.locator('main#main-content').getByLabel('Select a Date')).toHaveValue(picked);
      await expect(page.locator('main#main-content').getByTestId('tour-booking-card-slot').getByText(formatCurrency(base * 2, 'EUR', 'en'), { exact: true })).toBeVisible();
      const cardLoginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
      expect(new URL(cardLoginHref!, 'http://bookly.test').searchParams.get('returnUrl')).toBe(
        `/en/booking?tour=${slug}&participants=2&date=${picked}`,
      );

      // Sticky geometry: beside practical content and reviews the card holds
      // the shared nav offset and stays bounded by the article column.
      await page.locator('#reviews').scrollIntoViewIfNeeded();
      const cardBox = await page.locator('main#main-content').getByTestId('tour-booking-card-slot').boundingBox();
      const asideBox = await page
        .locator('main#main-content aside', { has: page.getByTestId('tour-booking-card-slot') })
        .boundingBox();
      expect(cardBox).not.toBeNull();
      expect(asideBox).not.toBeNull();
      // Measured offset (migrated from the old hardcoded 80px): the card sits
      // one shared token gap below the live sticky nav, read from real
      // computed styles and card geometry. Tight bound preserved, not loosened.
      const stuckNavBox = await page.getByRole('navigation', { name: 'Tour sections' }).boundingBox();
      expect(stuckNavBox).not.toBeNull();
      const tokenGapPx = await page.evaluate(() => {
        const root = getComputedStyle(document.documentElement);
        const raw = root.getPropertyValue('--spacing-inline').trim();
        const rootPx = parseFloat(getComputedStyle(document.documentElement).fontSize);
        const value = parseFloat(raw);
        return raw.endsWith('rem') ? value * rootPx : value;
      });
      expect(tokenGapPx).toBeGreaterThan(0);
      expect(
        Math.abs(cardBox!.y - (stuckNavBox!.y + stuckNavBox!.height + tokenGapPx)),
      ).toBeLessThanOrEqual(2);
      expect(cardBox!.y + cardBox!.height).toBeLessThanOrEqual(asideBox!.y + asideBox!.height + 2);

      // Back to mobile: the bar still shows the identical selection.
      await page.setViewportSize({ width: 390, height: 844 });
      await expect(page.locator('main#main-content').getByTestId('mobile-bar-summary')).toContainText('2');
      const barReturnAfter = new URL(
        (await page.locator('main#main-content').getByTestId('mobile-booking-bar').getByRole('link', { name: 'Continue to booking' }).getAttribute('href'))!,
        'http://bookly.test',
      ).searchParams.get('returnUrl');
      expect(barReturnAfter).toBe(`/en/booking?tour=${slug}&participants=2&date=${picked}`);
    } finally {
      await harness.stop();
    }
  });

  test('US2 auth cross-links preserve the full booking query from login to register and back', async ({ page, request }) => {
    test.setTimeout(120000);
    const slug = 'hidden-gems-rome-walking-tour';
    const response = await request.get(`/api/public/tours/${slug}?locale=en`);
    expect(response.ok()).toBeTruthy();
    const { data } = await response.json();
    const dates: string[] = data?.availability?.available_dates ?? [];
    expect(dates.length).toBeGreaterThan(10);
    const booking = `/en/booking?tour=${slug}&participants=2&date=${dates[10]}`;

    await page.goto(`/en/auth/login?returnUrl=${encodeURIComponent(booking)}`);
    const registerHref = await page.getByRole('link', { name: /create account/i }).getAttribute('href');
    expect(registerHref).toBe(`/en/auth/register?returnUrl=${encodeURIComponent(booking)}`);

    await page.getByRole('link', { name: /create account/i }).click();
    await expect(page).toHaveURL(/\/en\/auth\/register\?returnUrl=/);
    expect(new URL(page.url()).searchParams.get('returnUrl')).toBe(booking);

    const loginLink = page.locator('#main-content').getByRole('link', { name: /sign in/i });
    const loginHref = await loginLink.getAttribute('href');
    expect(loginHref).toBe(`/en/auth/login?returnUrl=${encodeURIComponent(booking)}`);

    await loginLink.click();
    const loginForm = page.getByRole('form', { name: 'Welcome back', exact: true });
    await expect(loginForm).toBeVisible();
    await loginForm.locator('input[name="email"]').fill('test@example.com');
    await loginForm.locator('input[name="password"]').fill('Password123!');
    await expect(loginForm.locator('input[name="email"]')).toHaveValue('test@example.com');
    await loginForm.getByRole('button', { name: 'Sign In', exact: true }).click();

    // Seeded login succeeds and returns to the full booking query untouched.
    await expect(page).toHaveURL(`/en/booking?tour=${slug}&participants=2&date=${dates[10]}`);
    await expect(page.getByRole('button', { name: /Confirm & Pay/i })).toBeEnabled();
  });

  test('US2 SSR exposes all 60 offered dates with no slice', async ({ page }) => {    test.setTimeout(180000);
    const slug = 'hidden-gems-rome-walking-tour';
    const complete = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => complete);
    try {
      const served = await readServedDetail(harness.mockPort, slug, 'en');
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);

      const values = await page.locator('main#main-content').getByLabel('Select a Date').evaluateAll(
        (selects) => selects.flatMap((select) =>
          Array.from((select as HTMLSelectElement).options).map((option) => option.value),
        ),
      );
      for (const date of served.availability.available_dates) {
        expect(values).toContain(date);
      }
      expect(values).toContain(served.availability.available_dates[31]);
    } finally {
      await harness.stop();
    }
  });

  test('US2 signed-out SSR preserves the full query through login and registration links', async ({ page }) => {
    test.setTimeout(180000);
    const slug = 'hidden-gems-rome-walking-tour';
    const complete = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => complete);
    try {
      const served = await readServedDetail(harness.mockPort, slug, 'en');
      const picked = served.availability.available_dates[4];
      await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);

      if ((page.viewportSize()?.width ?? 0) < 1024) {
        await page.getByTestId('mobile-booking-bar').getByRole('button').click();
      }
      await page.getByRole('combobox', { name: 'Select a Date', exact: true }).selectOption(picked);
      const expected = `/en/booking?tour=${slug}&participants=1&date=${picked}`;
      const loginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
      expect(loginHref).toBe(`/en/auth/login?returnUrl=${encodeURIComponent(expected)}`);
      const registerHref = await page.getByRole('link', { name: /crear cuenta|create account|crea account/i }).first().getAttribute('href');
      expect(registerHref).toBe(`/en/auth/register?returnUrl=${encodeURIComponent(expected)}`);

      // Following the login link keeps the full returnUrl for post-login return.
      await page.getByRole('link', { name: 'Book Now' }).first().click();
      await expect(page).toHaveURL(/\/en\/auth\/login\?returnUrl=/);
      expect(new URL(page.url()).searchParams.get('returnUrl')).toBe(expected);
    } finally {
      await harness.stop();
    }
  });

  test('US2 SSR UTC boundary never books today and accepts an explicit future date', async ({ page }) => {
    test.setTimeout(180000);
    const slug = 'hidden-gems-rome-walking-tour';
    const runtimeToday = new Date().toISOString().slice(0, 10);
    const complete = createFullTourDetail(new Date());
    complete.availability = {
      available_dates: [runtimeToday, ...complete.availability.available_dates],
      next_available_date: runtimeToday,
      is_unavailable: false,
    };
    const harness = await startSsrHarness(slug, () => complete);
    try {
      const served = await readServedDetail(harness.mockPort, slug, 'en');
      const future = served.availability.available_dates.find((date) => date > runtimeToday)!;
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);

      // Today is listed but strictly rejected: no automatic choice, no link.
      // The desktop card slot owns the live select (a hidden streamed copy
      // exists); wait for the enabled control before asserting.
      const mobile = (page.viewportSize()?.width ?? 0) < 1024;
      if (mobile) await page.getByTestId('mobile-booking-bar').getByRole('button').click();
      const selectionSurface = mobile
        ? page.getByRole('dialog')
        : page.locator('main#main-content').getByTestId('tour-booking-card-slot');
      const dateSelect = selectionSurface.getByRole('combobox', { name: 'Select a Date', exact: true });
      await expect(dateSelect).toBeEnabled();
      await expect(dateSelect).toHaveValue('');
      await expect(selectionSurface.getByText('Select an offered date to continue.')).toBeVisible();
      await expect(page.getByRole('link', { name: 'Book Now' })).toHaveCount(0);

      // An explicit offered future date recovers continuation.
      await dateSelect.selectOption(future);
      const loginHref = await page.getByRole('link', { name: 'Book Now' }).first().getAttribute('href');
      expect(new URL(loginHref!, 'http://bookly.test').searchParams.get('returnUrl')).toContain(`date=${future}`);
    } finally {
      await harness.stop();
    }
  });

  test('US2 SSR unavailable tour exposes no enabled checkout on either surface', async ({ page }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 390, height: 844 });
    const slug = 'hidden-gems-rome-walking-tour';
    const unavailable = createUnavailableTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => unavailable);
    try {
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);

      await expect(page.locator('main#main-content').getByText('Currently Unavailable')).toBeVisible();
      await expect(page.getByRole('link', { name: 'Book Now' })).toHaveCount(0);
      await expect(page.locator('main#main-content').getByTestId('mobile-booking-bar')).toHaveCount(0);
    } finally {
      await harness.stop();
    }
  });
});

// Spec 020 US6: localized route states through the real built SSR server.
// Controlled non-200 readings flow through the same no-store read and client
// status mapping as production (404→NotFoundError, 410→GoneError,
// 5xx→recoverable boundary). Browser route interception could never prove
// these paths because the page fetches on the server.
test.describe('Tour Detail route states (Spec 020 US6)', () => {
  const slug = 'hidden-gems-rome-walking-tour';

  for (const scenario of [
    { locale: 'en', title: 'Tour not found' },
    { locale: 'es', title: 'Tour no encontrado' },
    { locale: 'it', title: 'Tour non trovato' },
  ] as const) {
    test(`US6 SSR unknown slug renders localized 404 with noindex in ${scenario.locale}`, async ({ page }) => {
      test.setTimeout(180000);
      const complete = createFullTourDetail(new Date());
      const harness = await startSsrHarness(slug, () => complete);
      try {
        // The fixture server 404s any slug it does not own, exactly like an
        // unknown or unpublished tour on the real API.
        const unknownUrl = `http://127.0.0.1:${harness.nextPort}/${scenario.locale}/tours/this-tour-does-not-exist-xyz`;
        await page.goto(unknownUrl);
        await expect(page.locator('main#main-content')).toBeVisible();
        await expect(page.getByRole('heading', { name: scenario.title })).toBeVisible();
        const robotsContents = await page.locator('meta[name="robots"]').evaluateAll((els) =>
          els.map((el) => el.getAttribute('content') ?? ''),
        );
        expect(robotsContents.length).toBeGreaterThan(0);
        for (const robotsContent of robotsContents) expect(robotsContent).toMatch(/noindex/);
        await expect(page.getByRole('link', { name: /Book Now|Reservar Ahora|Prenota Ora/ })).toHaveCount(0);
        expect(page.url()).toBe(unknownUrl);
      } finally {
        await harness.stop();
      }
    });
  }

  for (const scenario of [
    { locale: 'en', title: 'This tour is no longer available', description: 'This tour was archived and cannot be booked.' },
    { locale: 'es', title: 'Este tour ya no está disponible', description: 'Este tour fue archivado y no se puede reservar.' },
  ] as const) {
    test(`US6 SSR archived tour renders localized withdrawn with no booking in ${scenario.locale}`, async ({ page }) => {
      test.setTimeout(180000);
      const current: SsrReading = { status: 410, body: { message: 'Tour archived.' } };
      const harness = await startSsrHarness(slug, () => current);
      try {
        const served = await fetchServedResponse(harness.mockPort, slug, scenario.locale);
        expect(served.status).toBe(410);

        const url = `http://127.0.0.1:${harness.nextPort}/${scenario.locale}/tours/${slug}`;
        await page.goto(url);
        await expect(page.locator('main#main-content')).toBeVisible();
        await expect(page.getByRole('heading', { name: scenario.title })).toBeVisible();
        await expect(page.locator('main#main-content').getByText(scenario.description)).toBeVisible();
        const withdrawnRobots = await page.locator('meta[name="robots"]').evaluateAll((els) =>
          els.map((el) => el.getAttribute('content') ?? ''),
        );
        expect(withdrawnRobots.length).toBeGreaterThan(0);
        for (const robotsContent of withdrawnRobots) expect(robotsContent).toMatch(/noindex/);
        await expect(page.getByRole('link', { name: /Book Now|Reservar Ahora|Prenota Ora/ })).toHaveCount(0);
        await expect(page.getByRole('button', { name: /Book Now|Reservar Ahora|Prenota Ora/ })).toHaveCount(0);
        // A withdrawn tour carries no offer: there is no TouristTrip schema to agree with.
        await expect(page.locator('script[type="application/ld+json"]')).toHaveCount(0);
        expect(page.url()).toBe(url);
      } finally {
        await harness.stop();
      }
    });
  }

  test('US6 SSR recoverable failure shows a safe localized retry and recovers without leaking upstream text', async ({ page }) => {
    test.setTimeout(180000);
    const complete = createFullTourDetail(new Date());
    let current: SsrReading = { status: 503, body: { message: 'hostile upstream SECRET-XYZ-PRIVATE payload' } };
    const harness = await startSsrHarness(slug, () => current);
    try {
      const served = await fetchServedResponse(harness.mockPort, slug, 'en');
      expect(served.status).toBe(503);

      const url = `http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`;
      await page.goto(url);
      await expect(page.locator('main#main-content')).toBeVisible();
      await expect(page.getByRole('heading', { level: 1, name: 'This tour could not be loaded', exact: true })).toBeVisible();
      const retry = page.getByRole('button', { name: 'Try again' });
      await expect(retry).toBeVisible();
      await expect(page.getByText('SECRET-XYZ-PRIVATE')).toHaveCount(0);
      const recoverableRobots = await page.locator('meta[name="robots"]').evaluateAll((els) =>
        els.map((el) => el.getAttribute('content') ?? ''),
      );
      expect(recoverableRobots.length).toBeGreaterThan(0);
      for (const robotsContent of recoverableRobots) expect(robotsContent).toMatch(/noindex/);

      // The same safe retry re-reads the recovered source with no navigation.
      current = complete;
      await retry.click();
      await expect(page.getByRole('heading', { level: 1 })).toHaveText(complete.title);
      await expect(page.getByText('SECRET-XYZ-PRIVATE')).toHaveCount(0);
      expect(page.url()).toBe(url);
    } finally {
      await page.context().unrouteAll({ behavior: 'wait' });
      await harness.stop();
    }
  });

  // Bounded per-status recoverable coverage: 403/422/429 each render the same
  // generic localized ErrorState (only NotFound becomes notFound, only Gone
  // becomes withdrawn) with noindex and no upstream payload. Full retry
  // recovery is proven once by the 503 case above, not repeated per status.
  for (const scenario of [
    { status: 403, locale: 'es' as const, title: 'No se pudo cargar este tour', description: 'Inténtalo de nuevo. No se ha realizado ningún pago ni reserva.', retry: 'Volver a intentar' },
    { status: 422, locale: 'it' as const, title: 'Impossibile caricare questo tour', description: 'Riprova. Nessun pagamento o prenotazione è stato effettuato.', retry: 'Riprova' },
    { status: 429, locale: 'en' as const, title: 'This tour could not be loaded', description: 'Please try again. No payment or booking was made.', retry: 'Try again' },
  ]) {
    test(`US6 SSR status ${scenario.status} renders safe localized recoverable state in ${scenario.locale}`, async ({ page }) => {
      test.setTimeout(180000);
      const current: SsrReading = {
        status: scenario.status,
        body: { message: 'hostile upstream SECRET-XYZ-PRIVATE payload', errors: { date: ['invalid'] } },
      };
      const harness = await startSsrHarness(slug, () => current);
      try {
        const served = await fetchServedResponse(harness.mockPort, slug, scenario.locale);
        expect(served.status).toBe(scenario.status);

        const url = `http://127.0.0.1:${harness.nextPort}/${scenario.locale}/tours/${slug}`;
        await page.goto(url);
        await expect(page.locator('main#main-content')).toBeVisible();
        const alert = page.locator('main#main-content').getByRole('alert');
        await expect(page.getByRole('heading', { level: 1, name: scenario.title, exact: true })).toBeVisible();
        await expect(alert).toContainText(scenario.description);
        await expect(page.getByRole('button', { name: scenario.retry })).toBeVisible();
        await expect(page.getByText('SECRET-XYZ-PRIVATE')).toHaveCount(0);
        await expect(page.getByText('invalid')).toHaveCount(0);
        const statusRobots = await page.locator('meta[name="robots"]').evaluateAll((els) =>
          els.map((el) => el.getAttribute('content') ?? ''),
        );
        expect(statusRobots.length).toBeGreaterThan(0);
        for (const robotsContent of statusRobots) expect(robotsContent).toMatch(/noindex/);
        await expect(page.getByRole('link', { name: /Book Now|Reservar Ahora|Prenota Ora/ })).toHaveCount(0);
        expect(page.url()).toBe(url);
      } finally {
        await harness.stop();
      }
    });
  }

  test('US6 SSR intentional-empty translated itinerary stays empty with agreeing schema', async ({ page }) => {
    test.setTimeout(180000);
    const complete = createFullTourDetail(new Date());
    const intentionalEmpty: SsrReading = {
      ...complete,
      title: 'Titolo italiano attuale',
      description: 'Descrizione italiana attuale.',
      content_locale: 'it',
      itinerary: [],
      itinerary_locale: 'it',
      translation_status: 'ready',
      translation_warning: undefined,
      seo: { ...complete.seo, meta_title: 'Titolo italiano attuale | Bookly', meta_description: 'Descrizione italiana attuale.' },
    };
    const harness = await startSsrHarness(slug, () => intentionalEmpty);
    try {
      const served = await readServedDetail(harness.mockPort, slug, 'it');
      expect(served.content_locale).toBe('it');
      expect(served.itinerary).toEqual([]);

      const url = `http://127.0.0.1:${harness.nextPort}/it/tours/${slug}`;
      await page.goto(url);
      await expect(page.getByRole('heading', { level: 1 })).toHaveText('Titolo italiano attuale');
      await expect(page.locator('#itinerary')).toHaveCount(0);
      await expect(page.getByText("L'itinerario è mostrato in inglese")).toHaveCount(0);
      await expect(page.getByText('Il contenuto è mostrato in inglese')).toHaveCount(0);
      const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
      expect(schema.itinerary).toBeUndefined();
      expect(schema['@context'].name['@language']).toBe('it');
      expect(schema.mainEntityOfPage.inLanguage).toBe('it');
      expect(page.url()).toBe(url);
    } finally {
      await harness.stop();
    }
  });
});

// Spec 020 US3: live canonical gallery through the real built SSR server.
// Controlled fixture readings (no shared-data writes) prove count/order,
// cover-first, sparse span, SeeAll reachability, viewer viewport/focus/inert
// behavior, real touch drag, honest broken slots and alt language. Next16.2
// also renders hidden streamed nodes, so visible-main scoping
// (main#main-content) is used wherever a testid/text locator could match a
// hidden copy; role queries stay default visible-only and the portaled dialog
// stays role-scoped. On native hosts the owned media adapter (flag-gated in
// the SSR helper) forwards only cdn.bookly.test optimizer sources to the
// owned localhost:8080 media server with real statuses/bytes; Docker runs
// leave the flag unset against the real CDN. Adapter runs prove gallery
// behavior, never the production optimizer.
test.describe('Tour Detail gallery (Spec 020 US3)', () => {
  const slug = 'hidden-gems-rome-walking-tour';

  test('US3 SSR gallery covers 0, 1 and 4 photos with full count, cover-first order and sparse span', async ({ page }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 1440, height: 900 });
    const empty = createFullTourDetail(new Date(), { images: [] });
    let current: TourDetail = empty;
    const harness = await startSsrHarness(slug, () => current);
    try {
      const url = `http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`;

      // Zero photos: honest empty state, no triggers, no viewer.
      await page.goto(url);
      const main = page.locator('main#main-content');
      await expect(main.getByTestId('tour-gallery-empty')).toBeVisible();
      await expect(main.getByText('No photos are available for this tour.')).toBeVisible();
      await expect(page.getByRole('button', { name: /View image|See all/ })).toHaveCount(0);
      await expect(page.getByRole('dialog')).toHaveCount(0);

      // One photo: a single tile, no rail arrows, no See all, viewer 1 of 1.
      current = createFullTourDetail(new Date(), {
        images: [{ url: 'http://cdn.bookly.test/tours/rome-cover.jpg', is_cover: true, alt: 'Only photo', alt_locale: 'en' }],
      });
      await page.reload();
      const single = page.locator('main#main-content').getByTestId('tour-gallery');
      await expect(single).toBeVisible();
      await expect(single.getByRole('button', { name: 'View image 1' })).toBeVisible();
      await expect(single.getByRole('button', { name: /Previous image|Next image/ })).toHaveCount(0);
      await expect(single.getByRole('button', { name: /See all/ })).toHaveCount(0);
      await single.getByRole('button', { name: 'View image 1' }).click();
      const singleDialog = page.getByRole('dialog', { name: 'Image lightbox' });
      await expect(singleDialog).toBeVisible();
      await expect(singleDialog.getByText('1 of 1')).toBeVisible();
      await expect(singleDialog.getByRole('button', { name: /Previous image|Next image/ })).toHaveCount(0);
      await page.keyboard.press('Escape');
      await expect(singleDialog).toHaveCount(0);

      // Four photos with the cover supplied last: cover first, caller order
      // kept, full real count, last tile spans the remaining row, no padding.
      const coverUrl = 'http://cdn.bookly.test/tours/rome-cover.jpg';
      const secondUrl = 'http://cdn.bookly.test/tours/rome-gallery-1.jpg';
      const thirdUrl = 'http://cdn.bookly.test/tours/rome-gallery-2.jpg';
      const fourthUrl = 'http://cdn.bookly.test/tours/rome-gallery-3.jpg';
      current = createFullTourDetail(new Date(), {
        images: [
          { url: secondUrl, is_cover: false, alt: 'Second', alt_locale: 'en' },
          { url: thirdUrl, is_cover: false, alt: 'Third', alt_locale: 'en' },
          { url: fourthUrl, is_cover: false, alt: 'Fourth', alt_locale: 'en' },
          { url: coverUrl, is_cover: true, alt: 'Cover', alt_locale: 'en' },
        ],
      });
      await page.reload();
      const served = await readServedDetail(harness.mockPort, slug, 'en');
      expect(served.images.map((image) => image.url)).toEqual([secondUrl, thirdUrl, fourthUrl, coverUrl]);
      const gallery = page.locator('main#main-content').getByTestId('tour-gallery');
      const tiles = gallery.getByRole('button', { name: /View image \d+/ });
      await expect(tiles).toHaveCount(4);
      const srcs = await tiles.getByRole('img').evaluateAll((imgs) =>
        imgs.map((img) => img.getAttribute('src') ?? ''),
      );
      for (const [index, expectedUrl] of [coverUrl, secondUrl, thirdUrl, fourthUrl].entries()) {
        expect(srcs[index]).toContain(encodeURIComponent(expectedUrl));
      }
      // Sparse contract, genuine dimensions with ceil-pixel tolerance (not
      // classname-only): the last tile spans the remaining right row, so it
      // matches the cover width and its right edge meets the track edge with
      // no unused area and no overflow.
      await expect(tiles.nth(3)).toHaveClass(/lg:col-span-2/);
      const track = gallery.getByTestId('tour-gallery-track');
      const sparseBoxes = await tiles.evaluateAll((els) =>
        els.map((el) => {
          const rect = el.getBoundingClientRect();
          return { left: rect.left, right: rect.right, width: rect.width };
        }),
      );
      const trackRect = (await track.boundingBox())!;
      expect(Math.abs(sparseBoxes[3].width - sparseBoxes[0].width)).toBeLessThanOrEqual(2);
      expect(Math.abs(sparseBoxes[3].right - trackRect.x - trackRect.width)).toBeLessThanOrEqual(2);
      expect(await track.evaluate((el) => el.scrollWidth)).toBeLessThanOrEqual(
        (await track.boundingBox())!.width + 1,
      );
      await expect(gallery.getByRole('button', { name: 'See all 4 photos', exact: true })).toBeVisible();
    } finally {
      await harness.stop();
    }
  });

  test('US3 SSR seven photos expose See all beyond five with full viewer reachability', async ({ page }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 1440, height: 900 });
    // Slots 6-7 reuse existing owned assets with unique queries (distinct
    // identities, authentic bytes) since the fixture CDN holds cover + 1..4.
    const images = [
      { url: 'http://cdn.bookly.test/tours/rome-cover.jpg', is_cover: true, alt: 'US3 photo 1', alt_locale: 'en' as const },
      ...[1, 2, 3, 4].map((n) => ({
        url: `http://cdn.bookly.test/tours/rome-gallery-${n}.jpg`,
        is_cover: false,
        alt: `US3 photo ${n + 1}`,
        alt_locale: 'en' as const,
      })),
      { url: 'http://cdn.bookly.test/tours/rome-gallery-1.jpg?us3-slot6', is_cover: false, alt: 'US3 photo 6', alt_locale: 'en' as const },
      { url: 'http://cdn.bookly.test/tours/rome-gallery-2.jpg?us3-slot7', is_cover: false, alt: 'US3 photo 7', alt_locale: 'en' as const },
    ];
    const full = createFullTourDetail(new Date(), { images });
    const harness = await startSsrHarness(slug, () => full);
    try {
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
      const gallery = page.locator('main#main-content').getByTestId('tour-gallery');
      await expect(gallery).toBeVisible();
      // getByRole is visible-only: 5 on the desktop mosaic. The full DOM
      // count (including lg:hidden rail slots) proves all 7 mounted photos,
      // and the viewer below proves every one reachable.
      await expect(gallery.getByRole('button', { name: /View image \d+/ })).toHaveCount(5);
      await expect(gallery.getByTestId('tour-gallery-track').locator('button')).toHaveCount(7);

      const seeAll = page.getByRole('button', { name: 'See all 7 photos' });
      await expect(seeAll).toBeVisible();
      await seeAll.click();
      const dialog = page.getByRole('dialog', { name: 'Image lightbox' });
      await expect(dialog).toBeVisible();
      await expect(dialog.getByText('1 of 7')).toBeVisible();
      for (let step = 2; step <= 7; step += 1) {
        await dialog.getByRole('button', { name: 'Next image' }).click();
        await expect(dialog.getByText(`${step} of 7`)).toBeVisible();
      }
      await expect(dialog.locator('img')).toHaveAttribute('alt', 'US3 photo 7');
      // Wraparound returns to the first photo.
      await dialog.getByRole('button', { name: 'Next image' }).click();
      await expect(dialog.getByText('1 of 7')).toBeVisible();
      await page.keyboard.press('Escape');
      await expect(dialog).toHaveCount(0);
      await expect(seeAll).toBeFocused();
    } finally {
      await harness.stop();
    }
  });

  test('US3 SSR viewer fills the viewport, navigates from close without moving focus, and restores the tile', async ({ page }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 1440, height: 900 });
    const images = [1, 2, 3, 4, 5].map((n) => ({
      url: n === 1 ? 'http://cdn.bookly.test/tours/rome-cover.jpg' : `http://cdn.bookly.test/tours/rome-gallery-${n - 1}.jpg`,
      is_cover: n === 1,
      alt: `US3 photo ${n}`,
      alt_locale: 'en' as const,
    }));
    const full = createFullTourDetail(new Date(), { images });
    const harness = await startSsrHarness(slug, () => full);
    try {
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
      const tile = page.getByRole('button', { name: 'View image 3' });
      await expect(tile).toBeVisible();
      await tile.focus();
      await page.keyboard.press('Enter');

      // Full viewport panel with the tile's own original index.
      const dialog = page.getByRole('dialog', { name: 'Image lightbox' });
      await expect(dialog).toBeVisible();
      const box = await dialog.boundingBox();
      expect(box).not.toBeNull();
      expect(box!.x).toBe(0);
      expect(box!.y).toBe(0);
      expect(box!.width).toBe(1440);
      expect(box!.height).toBe(900);
      await expect(dialog.locator('img')).toHaveAttribute('alt', 'US3 photo 3');
      await expect(dialog.getByText('3 of 5')).toBeVisible();

      // One dialog only: no nested locks. Background is locked and inert.
      await expect(page.getByRole('dialog')).toHaveCount(1);
      expect(await page.evaluate(() => document.body.style.overflow)).toBe('hidden');
      expect(await page.evaluate(() => (document.querySelector('main#main-content') as HTMLElement | null)?.inert === true)).toBe(true);

      // Arrow navigation from the close button keeps focus on close.
      const close = page.getByRole('button', { name: 'Close lightbox' });
      await expect(close).toBeFocused();
      await page.keyboard.press('ArrowRight');
      await expect(dialog.getByText('4 of 5')).toBeVisible();
      await expect(close).toBeFocused();
      await page.keyboard.press('ArrowLeft');
      await expect(dialog.getByText('3 of 5')).toBeVisible();
      await expect(close).toBeFocused();

      // Escape closes and returns focus to the actual invoking tile.
      await page.keyboard.press('Escape');
      await expect(dialog).toHaveCount(0);
      await expect(tile).toBeFocused();
      expect(await page.evaluate(() => document.body.style.overflow)).toBe('');
      expect(await page.evaluate(() => (document.querySelector('main#main-content') as HTMLElement | null)?.inert === true)).toBe(false);
    } finally {
      await harness.stop();
    }
  });

  test('US3 SSR mobile rail touch drag advances the counter with real touch input', async ({ browser }) => {
    test.setTimeout(180000);
    const full = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => full);
    // Touch-capable owned context with an absolute SSR URL (no baseURL needed).
    const touchContext = await browser.newContext({ hasTouch: true, viewport: { width: 390, height: 844 } });
    await enableNativeMediaProxy(touchContext);
    const touchPage = await touchContext.newPage();
    try {
      await touchPage.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
      const gallery = touchPage.locator('main#main-content').getByTestId('tour-gallery');
      await expect(gallery).toBeVisible();
      await expect(gallery.getByRole('list', { name: /thumbnail/i })).toHaveCount(0);
      const counter = gallery.getByText(/^\d+ of \d+$/);
      await expect(counter).toHaveText('1 of 5');

      // Actual browser touch input on the rail (not a scripted scrollLeft):
      // the gesture starts inside the visible track and every move stays
      // inside the physical viewport, advancing exactly one tile.
      const track = gallery.getByTestId('tour-gallery-track');
      await track.scrollIntoViewIfNeeded();
      const box = await track.boundingBox();
      expect(box).not.toBeNull();
      const startX = box!.x + box!.width * 0.75;
      const startY = box!.y + box!.height / 2;
      const swipe = box!.width * 0.6;
      expect(startX - swipe).toBeGreaterThan(0);
      const cdp = await touchContext.newCDPSession(touchPage);
      await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: startX, y: startY, id: 1 }] });
      const steps = 12;
      for (let step = 1; step <= steps; step += 1) {
        await cdp.send('Input.dispatchTouchEvent', {
          type: 'touchMove',
          touchPoints: [{ x: startX - (swipe * step) / steps, y: startY, id: 1 }],
        });
      }
      await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
      await expect(counter).toHaveText('2 of 5', { timeout: 10000 });
    } finally {
      await touchContext.unrouteAll({ behavior: 'wait' });
      await touchContext.close();
      await harness.stop();
    }
  });

  test('US3 SSR broken slot keeps stable count, dimensions and reachability through a URL swap', async ({ page }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 1440, height: 900 });
    const images = [1, 2, 3, 4, 5].map((n) => ({
      url: n === 1 ? 'http://cdn.bookly.test/tours/rome-cover.jpg' : `http://cdn.bookly.test/tours/rome-gallery-${n - 1}.jpg`,
      is_cover: n === 1,
      alt: `US3 photo ${n}`,
      alt_locale: 'en' as const,
    }));
    let current: TourDetail = createFullTourDetail(new Date(), { images });
    const harness = await startSsrHarness(slug, () => current);
    try {
      // Allowed CDN host with a known-missing path: the optimizer fetches it
      // through the owned media adapter and the ACTUAL owned HTTP404 is
      // observed on the image response (a disallowed host could never prove
      // a 404 because the optimizer rejects it before any request).
      const missingUrl = `http://cdn.bookly.test/tours/rome-gallery-missing.jpg?us3=${Date.now().toString(36)}`;
      current = createFullTourDetail(new Date(), {
        images: images.map((image, index) => (index === 2 ? { ...image, url: missingUrl } : image)),
      });
      const url = `http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`;
      const missingResponse = page.waitForResponse(
        (response) => response.url().includes('_next/image') && response.url().includes('rome-gallery-missing'),
      );
      await page.goto(url);
      expect((await missingResponse).status()).toBe(404);
      const gallery = page.locator('main#main-content').getByTestId('tour-gallery');
      const tiles = gallery.getByRole('button', { name: /View image \d+/ });
      await expect(tiles).toHaveCount(5);
      // Exactly one honest placeholder; every slot keeps a nonzero box.
      // Mosaic contract with ceil-pixel tolerance (not exact unique counts):
      // support quarters share dimensions within 1px, and the cover spans
      // two quarter columns plus the 8px grid gap within 2px.
      await expect(gallery.getByText('This photo is unavailable.')).toHaveCount(1);
      const boxes = await tiles.evaluateAll((els) =>
        els.map((el) => {
          const rect = el.getBoundingClientRect();
          return { width: rect.width, height: rect.height };
        }),
      );
      expect(boxes.every((box) => box.width > 0 && box.height > 0)).toBe(true);
      const quarter = boxes[1];
      for (const index of [2, 3, 4]) {
        expect(Math.abs(boxes[index].width - quarter.width)).toBeLessThanOrEqual(1);
        expect(Math.abs(boxes[index].height - quarter.height)).toBeLessThanOrEqual(1);
      }
      expect(Math.abs(boxes[0].width - (2 * quarter.width + 8))).toBeLessThanOrEqual(2);
      expect(Math.abs(boxes[0].height - (2 * quarter.height + 8))).toBeLessThanOrEqual(2);
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
        await page.evaluate(() => document.documentElement.clientWidth),
      );

      // Every photo stays reachable through the counter, then the broken
      // third photo is explicitly current: caption, counter and failure all
      // asserted together before the rest of the reachability claim.
      await tiles.nth(0).click();
      const dialog = page.getByRole('dialog', { name: 'Image lightbox' });
      await expect(dialog).toBeVisible();
      for (let step = 2; step <= 5; step += 1) {
        await dialog.getByRole('button', { name: 'Next image' }).click();
        await expect(dialog.getByText(`${step} of 5`)).toBeVisible();
      }
      await dialog.getByRole('button', { name: 'Previous image' }).click();
      await dialog.getByRole('button', { name: 'Previous image' }).click();
      await expect(dialog.getByText('3 of 5')).toBeVisible();
      await expect(dialog.locator('img')).toHaveCount(0);
      await expect(dialog.getByText('US3 photo 3', { exact: true })).toBeVisible();
      await expect(dialog.getByText('This photo is unavailable.')).toHaveCount(1);
      const reopenBoxes = await tiles.evaluateAll((els) =>
        els.map((el) => {
          const rect = el.getBoundingClientRect();
          return { width: rect.width, height: rect.height };
        }),
      );
      expect(reopenBoxes).toEqual(boxes);
      await page.keyboard.press('Escape');
      await expect(dialog).toHaveCount(0);

      // New SSR identity swaps the broken URL for an EXISTING owned asset
      // (plus query): the replacement must actually decode (naturalWidth>0)
      // with no failure placeholder. Same-component rerender recovery is
      // covered at unit level; the browser claims the new SSR identity only.
      current = createFullTourDetail(new Date(), {
        images: images.map((image, index) =>
          index === 2 ? { ...image, url: 'http://cdn.bookly.test/tours/rome-gallery-2.jpg?us3-recovered' } : image,
        ),
      });
      await page.reload();
      const recovered = page.locator('main#main-content').getByTestId('tour-gallery');
      await expect(recovered.getByRole('button', { name: /View image \d+/ })).toHaveCount(5);
      await expect(recovered.getByText('This photo is unavailable.')).toHaveCount(0);
      const recoveredImg = recovered.getByRole('button', { name: 'View image 3' }).locator('img');
      await expect
        .poll(() => recoveredImg.evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0))
        .toBe(true);
      await page.getByRole('button', { name: 'View image 1' }).click();
      const reopened = page.getByRole('dialog', { name: 'Image lightbox' });
      await expect(reopened).toBeVisible();
      for (let step = 2; step <= 5; step += 1) {
        await reopened.getByRole('button', { name: 'Next image' }).click();
        await expect(reopened.getByText(`${step} of 5`)).toBeVisible();
      }
      await expect(reopened.locator('img')).toHaveAttribute('alt', 'US3 photo 5');
      await page.keyboard.press('Escape');
      await expect(reopened).toHaveCount(0);
    } finally {
      await harness.stop();
    }
  });

  test('US3 SSR alt keeps actual language while controls follow the page locale', async ({ page }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 1440, height: 900 });
    const full = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => full);
    try {
      const served = await readServedDetail(harness.mockPort, slug, 'es');
      expect(served.content_locale).toBe('en');

      await page.goto(`http://127.0.0.1:${harness.nextPort}/es/tours/${slug}`);
      const gallery = page.locator('main#main-content').getByTestId('tour-gallery');
      await expect(gallery).toBeVisible();
      // Page-localized controls; authored EN alt declares lang=en.
      await expect(gallery.getByRole('button', { name: 'Ver imagen 1' })).toBeVisible();
      await expect(page.getByRole('button', { name: 'Ver las 5 fotos' })).toBeVisible();
      const tileImg = gallery.getByRole('button', { name: 'Ver imagen 1' }).locator('img');
      await expect(tileImg).toHaveAttribute('lang', 'en');

      await gallery.getByRole('button', { name: 'Ver imagen 1' }).click();
      const dialog = page.getByRole('dialog', { name: 'Galería ampliada' });
      await expect(dialog).toBeVisible();
      await expect(dialog.locator('img')).toHaveAttribute('lang', 'en');
      await expect(dialog.getByText('1 de 5')).toBeVisible();
      const closeLanguage = await page
        .getByRole('button', { name: 'Cerrar galería' })
        .evaluate((el: HTMLElement) => el.closest('[lang]')?.getAttribute('lang'));
      expect(closeLanguage).toBe('es');
      await page.keyboard.press('Escape');
      await expect(dialog).toHaveCount(0);
    } finally {
      await harness.stop();
    }
  });

  test('US3 SSR gallery keeps a stable box and no page overflow at 390 and 1440', async ({ page }) => {
    test.setTimeout(180000);
    const full = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => full);
    try {
      for (const width of [390, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
        const tiles = page.locator('main#main-content').getByTestId('tour-gallery').getByRole('button', { name: /View image \d+/ });
        await expect(tiles).toHaveCount(5);
        const boxes = await tiles.evaluateAll((els) =>
          els.map((el) => {
            const rect = el.getBoundingClientRect();
            return { width: rect.width, height: rect.height };
          }),
        );
        // No collapsed zero box anywhere. Rail tiles share one box within
        // 1px; the desktop mosaic keeps quarter equality with the cover at
        // two quarters plus the 8px grid gap within 2px.
        expect(boxes.every((box) => box.width > 0 && box.height > 0)).toBe(true);
        if (width === 390) {
          for (const box of boxes) {
            expect(Math.abs(box.height - boxes[0].height)).toBeLessThanOrEqual(1);
          }
        } else {
          for (const index of [1, 2, 3, 4]) {
            expect(Math.abs(boxes[index].width - boxes[1].width)).toBeLessThanOrEqual(1);
            expect(Math.abs(boxes[index].height - boxes[1].height)).toBeLessThanOrEqual(1);
          }
          expect(Math.abs(boxes[0].width - (2 * boxes[1].width + 8))).toBeLessThanOrEqual(2);
          expect(Math.abs(boxes[0].height - (2 * boxes[1].height + 8))).toBeLessThanOrEqual(2);
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
          await page.evaluate(() => document.documentElement.clientWidth),
        );
      }
    } finally {
      await harness.stop();
    }
  });
});

// Spec 020 US4 T053: reading-navigation browser acceptance through the real
// built SSR server. Controlled complete/sparse fixtures (no shared-data
// writes) prove native anchor intent, real heading focus clear of the
// measured sticky nav, passive aria tracking, horizontal-only rail reveal,
// resize, long-locale labels, reduced motion and deep-link restoration.
// No route interception (SSR would ignore it), no mocked observers, no
// sleeps: every wait targets real nav geometry, aria state or enabled UI
// readiness. Touch/gallery scenarios stay with US3; booking/wishlist/catalog
// behavior is untouched.
test.describe('Tour Detail section navigation (Spec 020 US4 T053)', () => {
  const slug = 'hidden-gems-rome-walking-tour';
  const fullIds = [
    'overview', 'facts', 'highlights', 'itinerary', 'included', 'excluded',
    'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related',
  ];

  async function navFor(page: Page, name: string) {
    const nav = page.getByRole('navigation', { name });
    await expect(nav).toBeVisible();
    return nav;
  }

  function headingFor(page: Page, id: string) {
    // Section-scoped inside the rendered main content: Next 16.2 hidden
    // streamed trees can duplicate IDs/testids, so a bare `#id` query may
    // resolve the wrong tree. .first() here selects within ONE section,
    // mirroring the island's own resolveHeading rule (first h1/h2/h3 in the
    // section) — it does not paper over ambiguous live UI. Role queries stay
    // visible-by-default and need no change.
    return page
      .locator('main#main-content')
      .locator(`#${id}`)
      .locator(':is(h1, h2, h3)')
      .first();
  }

  // Explicit activation by pointer click (keyboard path covered separately
  // below): updates the native hash, focuses the real heading, then — only
  // once scrolling finishes — requires the observer to agree with the
  // explicit target instead of overriding it with another visible heading.
  // Returns the focused heading for geometry checks.
  async function activateEntry(page: Page, navName: string, id: string, linkName: string) {
    const nav = await navFor(page, navName);
    const link = nav.getByRole('link', { name: linkName });
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(new RegExp(`#${id}$`));
    const heading = headingFor(page, id);
    await expect(heading).toBeFocused();
    await headingClearOfNav(page, navName, id);
    await expect(link).toHaveAttribute('aria-current', 'location');
    await expect(nav.locator('a[aria-current="location"]')).toHaveCount(1);
    return heading;
  }

  // Settle contract, not a clearance guess: the nav must actually be stuck
  // once the page scrolled, the section top must align with the live nav
  // bottom within real rounding — or, at the legitimate end-of-document
  // scroll limit (computed from real max scroll), the heading must still be
  // fully visible. Categorical result: no vacuous pass; missing boxes,
  // an unstuck nav, a misaligned section, or an obscured heading fail.
  async function headingClearOfNav(page: Page, navName: string, id: string, timeoutMs = 10000) {
    await expect
      .poll(async () => {
        return page.evaluate(
          ({ navLabel, sectionId }) => {
            const scope = document.querySelector('main#main-content');
            const nav = scope?.querySelector(`nav[aria-label="${navLabel}"]`);
            const section = scope?.querySelector(`#${CSS.escape(sectionId)}`);
            const heading = section?.querySelector('h1, h2, h3');
            if (!(nav instanceof HTMLElement) || !(section instanceof HTMLElement) || !heading) {
              return 'missing';
            }
            const navBox = nav.getBoundingClientRect();
            const sectionBox = section.getBoundingClientRect();
            const headBox = heading.getBoundingClientRect();
            const scrolled = window.scrollY > 1;
            const stuck = Math.abs(navBox.y) <= 1;
            const aligned = Math.abs(sectionBox.y - (navBox.y + navBox.height)) <= 2;
            const maxScroll =
              document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const atScrollEnd = window.scrollY >= maxScroll - 2;
            const headingVisible =
              headBox.y >= navBox.y + navBox.height - 1 &&
              headBox.y + headBox.height <= document.documentElement.clientHeight + 1;
            if (scrolled && !stuck) return 'nav-unstuck';
            if (!aligned && !atScrollEnd) return 'section-misaligned';
            if (!headingVisible) return 'heading-obscured';
            return 'settled';
          },
          { navLabel: navName, sectionId: id },
        );
      }, { timeout: timeoutMs })
      .toBe('settled');
  }

  async function noPageHorizontalOverflow(page: Page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
      await page.evaluate(() => document.documentElement.clientWidth),
    );
  }

  // Live shared token gap (spacing-inline): read from computed styles, never
  // hardcoded, so geometry probes track the actual token.
  async function sharedTokenGapPx(page: Page): Promise<number> {
    const gap = await page.evaluate(() => {
      const root = getComputedStyle(document.documentElement);
      const raw = root.getPropertyValue('--spacing-inline').trim();
      const rootPx = parseFloat(getComputedStyle(document.documentElement).fontSize);
      const value = parseFloat(raw);
      return raw.endsWith('rem') ? value * rootPx : value;
    });
    expect(gap).toBeGreaterThan(0);
    return gap;
  }

  test('US4 full tour activates first, middle and last entries with focused headings clear of the sticky nav', async ({ page }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 1280, height: 900 });
    const full = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => full);
    try {
      await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
      await expect(page.locator('main#main-content')).toBeVisible();
      await page.evaluate(() => document.fonts.ready);

      const nav = await navFor(page, 'Tour sections');
      const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
      expect(hrefs).toEqual(fullIds.map((id) => `#${id}`));

      for (const target of [
        { id: 'overview', name: 'About This Tour' },
        { id: 'itinerary', name: 'Itinerary' },
        { id: 'related', name: 'More experiences to explore' },
      ]) {
        await test.step(`activate ${target.id}`, async () => {
          await activateEntry(page, 'Tour sections', target.id, target.name);
        });
      }

      // One genuine keyboard path (Tab/Enter on a real enabled link proves
      // operability; full keyboard coverage stays with T069/a11y and is not
      // claimed from pointer clicks).
      await test.step('keyboard Tab/Enter activation', async () => {
        const navLinks = page.getByRole('navigation', { name: 'Tour sections' });
        await navLinks.getByRole('link', { name: 'About This Tour' }).focus();
        await page.keyboard.press('Tab');
        await expect(navLinks.getByRole('link', { name: 'Tour facts' })).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/#facts$/);
        await expect(headingFor(page, 'facts')).toBeFocused();
        await headingClearOfNav(page, 'Tour sections', 'facts');
        await expect(navLinks.getByRole('link', { name: 'Tour facts' })).toHaveAttribute(
          'aria-current',
          'location',
        );
      });

      // Stuck while deep in the page: sticky geometry, not a class assertion.
      const navBox = await nav.boundingBox();
      expect(navBox).not.toBeNull();
      expect(navBox!.y).toBeLessThanOrEqual(1);
      await noPageHorizontalOverflow(page);
    } finally {
      await harness.stop();
    }
  });

  test('US4 sparse tour exposes only present entries and activates them', async ({ page }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 1280, height: 900 });
    const sparse = createSparseTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => sparse);
    try {
      await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
      await expect(page.locator('main#main-content')).toBeVisible();

      const nav = await navFor(page, 'Tour sections');
      const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
      expect(hrefs).toEqual(['#overview', '#facts']);
      await expect(nav.getByRole('link', { name: 'Reviews' })).toHaveCount(0);

      await activateEntry(page, 'Tour sections', 'facts', 'Tour facts');
      await noPageHorizontalOverflow(page);
    } finally {
      await harness.stop();
    }
  });

  test('US4 passive scrolling follows reading position without moving focus', async ({ page }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 1280, height: 900 });
    const full = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => full);
    try {
      await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
      await expect(page.locator('main#main-content')).toBeVisible();

      // Explicit activation first: focus lands on the real overview heading.
      await activateEntry(page, 'Tour sections', 'overview', 'About This Tour');
      // Scoped to the rendered main content: a bare document query could hit
      // a hidden streamed duplicate tree instead of the live UI.
      const stillOnOverviewHeading = () =>
        page.evaluate(
          () =>
            document.activeElement ===
            document.querySelector('main#main-content #overview :is(h1, h2, h3)'),
        );
      expect(await stillOnOverviewHeading()).toBe(true);

      // Genuine browser scroll (not an activation): the observer must move
      // aria-current to Reviews while focus stays on the overview heading.
      await page.evaluate(() => {
        document
          .querySelector('main#main-content')
          ?.querySelector('#reviews')
          ?.scrollIntoView();
      });
      const nav = page.getByRole('navigation', { name: 'Tour sections' });
      await expect(nav.getByRole('link', { name: 'Reviews' })).toHaveAttribute('aria-current', 'location');
      expect(await stillOnOverviewHeading()).toBe(true);
      try {
        await headingClearOfNav(page, 'Tour sections', 'reviews');
      } finally {
        // Retain actual geometry for the mobile-UA passive-scroll regression.
        const geometry = await page.evaluate(() => {
          const scope = document.querySelector('main#main-content');
          const nav = scope?.querySelector('nav[aria-label="Tour sections"]');
          const section = scope?.querySelector('#reviews');
          const heading = section?.querySelector('h1, h2, h3');
          const box = (node: Element | null | undefined) => {
            if (!node) return null;
            const rect = node.getBoundingClientRect();
            return { top: rect.top, bottom: rect.bottom, height: rect.height };
          };
          return {
            nav: box(nav), section: box(section), heading: box(heading),
            scrollY, maxScroll: document.documentElement.scrollHeight - document.documentElement.clientHeight,
            clientHeight: document.documentElement.clientHeight, innerHeight,
            visualHeight: visualViewport?.height, scale: visualViewport?.scale, dpr: devicePixelRatio,
            scrollMargin: section ? getComputedStyle(section).scrollMarginTop : null,
          };
        });
        console.info('SPEC020_PASSIVE_NAV_GEOMETRY', JSON.stringify(geometry));
      }
      expect(await stillOnOverviewHeading()).toBe(true);
      await noPageHorizontalOverflow(page);
    } finally {
      await harness.stop();
    }
  });

  test('US4 narrow rail reveals the active entry without moving the page vertically', async ({ page, request }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 390, height: 844 });
    const full = createFullTourDetail(new Date());
    const reviewResponse = await request.get(`/api/public/tours/${slug}/reviews?page=1&per_page=5`);
    expect(reviewResponse.ok()).toBeTruthy();
    const reviews = (await reviewResponse.json()).data;
    expect(reviews.length).toBeGreaterThan(0);
    const harness = await startSsrHarness(slug, () => full);
    try {
      await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
      await expect(page.locator('main#main-content')).toBeVisible();
      await page.evaluate(() => document.fonts.ready);

      await expect(
        page.locator('main#main-content #reviews').getByText(reviews[0].reviewer_name, { exact: true }).first(),
      ).toBeVisible();

      const nav = await navFor(page, 'Tour sections');
      const lastLink = nav.getByRole('link', { name: 'More experiences to explore' });
      await lastLink.click();
      await expect(page).toHaveURL(/#related$/);
      await expect(headingFor(page, 'related')).toBeFocused();
      await expect(lastLink).toHaveAttribute('aria-current', 'location');
      await headingClearOfNav(page, 'Tour sections', 'related');
      await expect(lastLink).toHaveAttribute('aria-current', 'location');

      // Horizontal-only reveal: the active link is fully inside the 390px
      // viewport while the page itself never overflows horizontally, and the
      // heading rests exactly at the measured sticky obstruction (a vertical
      // nudge from the reveal would break this equality).
      await expect
        .poll(async () => {
          const box = await lastLink.boundingBox();
          if (!box) return 'hidden';
          return box.x >= -1 && box.x + box.width <= 391 ? 'revealed' : 'clipped';
        }, { timeout: 10000 })
        .toBe('revealed');
      const linkBox = await lastLink.boundingBox();
      expect(linkBox).not.toBeNull();
      expect(linkBox!.x).toBeGreaterThanOrEqual(-1);
      expect(linkBox!.x + linkBox!.width).toBeLessThanOrEqual(391);
      await expect(lastLink).toHaveAttribute('aria-current', 'location');
      await noPageHorizontalOverflow(page);

      // The full fixture is bookable, so the mobile bar must be present with
      // an actually enabled continue control — never an optional no-op — and
      // the settled heading sits above the live bar.
      const bar = page.locator('main#main-content').getByTestId('mobile-booking-bar');
      await expect(bar).toBeVisible();
      const barContinue = bar.getByRole('link', { name: 'Continue to booking' });
      await expect(barContinue).toBeVisible();
      await expect(barContinue).toBeEnabled();
      const barBox = await bar.boundingBox();
      const headBox = await headingFor(page, 'related').boundingBox();
      expect(barBox).not.toBeNull();
      expect(headBox).not.toBeNull();
      expect(headBox!.y + headBox!.height).toBeLessThanOrEqual(barBox!.y + 1);
    } finally {
      await harness.stop();
    }
  });

  test('US4 long locale labels survive resize with reachable entries and no page overflow', async ({ page }) => {
    test.setTimeout(180000);
    const full = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => full);
    try {
      await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
      await page.setViewportSize({ width: 390, height: 844 });
      await page.goto(`http://127.0.0.1:${harness.nextPort}/es/tours/${slug}`);
      await expect(page.locator('main#main-content')).toBeVisible();
      await page.evaluate(() => document.fonts.ready);

      await activateEntry(page, 'Secciones del tour', 'itinerary', 'Itinerario');
      await noPageHorizontalOverflow(page);

      // Real resize both ways: exactly one current entry, rail stays usable.
      await page.setViewportSize({ width: 1440, height: 900 });
      await expect(page.getByRole('navigation', { name: 'Secciones del tour' })).toBeVisible();
      await expect
        .poll(() => page.getByRole('navigation', { name: 'Secciones del tour' }).locator('a[aria-current="location"]').count())
        .toBe(1);
      await noPageHorizontalOverflow(page);

      await page.setViewportSize({ width: 390, height: 844 });
      await expect(page.getByRole('navigation', { name: 'Secciones del tour' })).toBeVisible();
      await expect
        .poll(() => page.getByRole('navigation', { name: 'Secciones del tour' }).locator('a[aria-current="location"]').count())
        .toBe(1);
      await noPageHorizontalOverflow(page);
    } finally {
      await harness.stop();
    }
  });

  test('US4 reduced motion targets the reading position immediately', async ({ page }) => {
    test.setTimeout(180000);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setViewportSize({ width: 1280, height: 900 });
    const full = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => full);
    try {
      await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
      await expect(page.locator('main#main-content')).toBeVisible();
      expect(
        await page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches),
      ).toBe(true);

      await activateEntry(page, 'Tour sections', 'facts', 'Tour facts');
      // Immediate targeting under reduced motion: re-assert the settled
      // heading on a tight budget rather than only an eventual long poll.
      await headingClearOfNav(page, 'Tour sections', 'facts', 3000);
      await noPageHorizontalOverflow(page);
    } finally {
      await harness.stop();
    }
  });

  test('US4 initial deep link restores the reading target', async ({ page }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 1280, height: 900 });
    const full = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => full);
    try {
      await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}#reviews`);
      await expect(page.locator('main#main-content')).toBeVisible();

      const nav = page.getByRole('navigation', { name: 'Tour sections' });
      await expect(nav.getByRole('link', { name: 'Reviews' })).toHaveAttribute('aria-current', 'location');
      await expect(headingFor(page, 'reviews')).toBeFocused();
      expect(page.url()).toContain('#reviews');
      await headingClearOfNav(page, 'Tour sections', 'reviews');
      await expect(nav.getByRole('link', { name: 'Reviews' })).toHaveAttribute('aria-current', 'location');
      await noPageHorizontalOverflow(page);
    } finally {
      await harness.stop();
    }
  });

  // Forthcoming-red geometry probe (T055/T056 integration target, NOT a
  // source change): at a realistic 200% nav text size the sticky booking card
  // must clear the measured nav obstruction. The hardcoded lg:top-20 (80px)
  // card slot cannot follow a taller nav, so the final no-overlap assertion
  // is expected to fail on current source; root records that red before the
  // integration phase. The injected style is browser-only and labeled as a
  // test-only accessibility text-resize probe.
  test('US4 sticky booking card clears the measured nav obstruction at 200% nav text size', async ({ page }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 1280, height: 900 });
    // Reduced motion matches root's actual reproduction and makes the scroll
    // settle immediate and deterministic.
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const full = createFullTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => full);
    try {
      await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
      await expect(page.locator('main#main-content')).toBeVisible();
      await page.evaluate(() => document.fonts.ready);

      const nav = await navFor(page, 'Tour sections');
      const cardSlot = page.locator('main#main-content').getByTestId('tour-booking-card-slot');
      await expect(cardSlot).toBeVisible();
      const aside = page.locator('main#main-content aside', { has: page.getByTestId('tour-booking-card-slot') });
      const baselineNavHeight = (await nav.boundingBox())!.height;
      expect(baselineNavHeight).toBeGreaterThan(0);

      // Browser-only text resize: real reflow, real ResizeObserver
      // notification, production source untouched. The 200%/2.0 pairing is a
      // usable text-resize premise shared with the unit slice.
      await page.addStyleTag({
        content:
          'nav[aria-label="Tour sections"] a{' +
          'font-size:200%!important;line-height:2!important}' +
          '/* T053 accessibility text-resize probe (test-only, production unchanged) */',
      });
      await expect
        .poll(async () => (await nav.boundingBox())?.height ?? 0, { timeout: 10000 })
        .toBeGreaterThan(baselineNavHeight);
      const grownNavHeight = (await nav.boundingBox())!.height;
      // Premise provenance: the nav must actually exceed the hardcoded 80px
      // card offset, or the overlap check below would prove nothing.
      expect(grownNavHeight).toBeGreaterThan(80);

      // Middle of the article (facts, per root's actual Rome confirmation):
      // plenty of real article remains below, so the card is top-stuck rather
      // than legitimately pushed up by the containing-block end.
      await activateEntry(page, 'Tour sections', 'facts', 'Tour facts');

      // Stuck premise: the nav sits at the viewport top...
      const stuckNavBox = await nav.boundingBox();
      expect(stuckNavBox).not.toBeNull();
      expect(Math.abs(stuckNavBox!.y)).toBeLessThanOrEqual(1);

      // ...and the aside keeps more than a token gap of real content below
      // the card, excluding container-end push-up by construction (push-up
      // begins at zero remaining; root actual: 528px remaining).
      const gapPx = await sharedTokenGapPx(page);
      const cardBox = await cardSlot.boundingBox();
      const asideBox = await aside.boundingBox();
      expect(cardBox).not.toBeNull();
      expect(asideBox).not.toBeNull();
      expect(asideBox!.y + asideBox!.height - (cardBox!.y + cardBox!.height)).toBeGreaterThan(gapPx);

      // Single geometry provenance check: actual nav bottom vs actual card
      // top. No classname assertions, no overbroad tolerance (±1px rounding).
      const geometry = await page.evaluate(() => {
        const stuckNav = document
          .querySelector('main#main-content')
          ?.querySelector('nav[aria-label="Tour sections"]');
        const slot = document
          .querySelector('main#main-content')
          ?.querySelector('[data-testid="tour-booking-card-slot"]');
        if (!(stuckNav instanceof HTMLElement) || !(slot instanceof HTMLElement)) return null;
        const navBox = stuckNav.getBoundingClientRect();
        const slotBox = slot.getBoundingClientRect();
        return { navBottom: navBox.y + navBox.height, cardTop: slotBox.y };
      });
      expect(geometry).not.toBeNull();
      expect(geometry!.cardTop).toBeGreaterThanOrEqual(geometry!.navBottom - 1);
      await noPageHorizontalOverflow(page);
    } finally {
      await harness.stop();
    }
  });
});

// Spec 020 US5: live trust and header wishlist integration. Real backend +
// real SSR page; controlled failure only at the named API boundary. Root
// owns execution and the full EN/ES/IT x width matrix; the loops below are
// the worker-authored final assertions for trust, header wishlist and
// readable-tour preservation.
test.describe('Tour Detail trust and header wishlist (Spec 020 US5)', () => {
  const slug = 'hidden-gems-rome-walking-tour';
  const reviewsWord: Record<string, string> = { en: 'Reviews', es: 'Reseñas', it: 'Recensioni' };
  const promptTitle: Record<string, string> = {
    en: 'Sign in to save tours',
    es: 'Inicia sesion para guardar tours',
    it: 'Accedi per salvare tour',
  };

  test('US5 header shows the genuine API score with exactly one current-tour wishlist control', async ({
    page,
    request,
  }) => {
    const response = await request.get(`/api/public/tours/${slug}?locale=en`);
    expect(response.ok()).toBeTruthy();
    const { data } = await response.json();
    const reviewCount = data.reviews.count as number;
    const reviewAverage = data.reviews.average_rating as number;
    expect(Number.isInteger(reviewCount) && reviewCount > 0).toBe(true);

    await page.goto(`/en/tours/${slug}`);
    await expect(page.locator('main#main-content')).toBeVisible();

    // Exactly one control carries the current tour id; related cards keep
    // their own distinct-tour controls, matching the API related list.
    const current = page.locator(`main#main-content [data-testid="wishlist-button"][data-tour-id="${data.id}"]`);
    await expect(current).toHaveCount(1);
    const related = Array.isArray(data.related_tours) ? data.related_tours : [];
    await expect(page.locator('main#main-content [data-testid="wishlist-button"]')).toHaveCount(
      1 + related.length,
    );

    // Genuine score: the API average digit plus the localized accessible
    // score name, and the review link into the real section.
    await expect(
      page.locator('main#main-content').getByText(reviewAverage.toFixed(1), { exact: true }).first(),
    ).toBeVisible();
    const score = page.locator(
      `main#main-content span[aria-label="Rating: ${reviewAverage} out of 5"]`,
    );
    await expect(score).toHaveCount(1);
    const reviewLink = page.getByRole('link', { name: `(${reviewCount} Reviews)` });
    await expect(reviewLink).toHaveAttribute('href', '#reviews');
  });

  test('US5 ES/IT header keeps the genuine score, review link and single current-tour control', async ({
    page,
    request,
  }) => {
    for (const locale of ['es', 'it'] as const) {
      const response = await request.get(`/api/public/tours/${slug}?locale=${locale}`);
      expect(response.ok()).toBeTruthy();
      const { data } = await response.json();
      const reviewCount = data.reviews.count as number;
      expect(Number.isInteger(reviewCount) && reviewCount > 0).toBe(true);

      await page.goto(`/${locale}/tours/${slug}`);
      await expect(page.locator('main#main-content')).toBeVisible();
      await expect(
        page.locator(`main#main-content [data-testid="wishlist-button"][data-tour-id="${data.id}"]`),
      ).toHaveCount(1);
      const score = page.locator(
        `main#main-content span[aria-label*="${data.reviews.average_rating}"]`,
      );
      await expect(score.first()).toBeVisible();
      await expect(
        page.getByRole('link', { name: `(${reviewCount} ${reviewsWord[locale]})` }),
      ).toHaveAttribute('href', '#reviews');
    }
  });

  test('US5 sparse SSR tour exposes no reviews section, nav entry or header score', async ({
    page,
  }) => {
    test.setTimeout(180000);
    const sparse = createSparseTourDetail(new Date());
    const harness = await startSsrHarness(slug, () => sparse);
    try {
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
      await expect(page.locator('main#main-content')).toBeVisible();
      await expect(page.locator('main#main-content #reviews')).toHaveCount(0);
      const nav = page.getByRole('navigation', { name: 'Tour sections' });
      await expect(nav.getByRole('link', { name: 'Reviews' })).toHaveCount(0);
      // No scored header badge without genuine reviews.
      await expect(page.locator('main#main-content span[aria-label*="out of 5"]')).toHaveCount(0);
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    } finally {
      await harness.stop();
    }
  });

  test('US5 signed-out header wishlist opens the shared dialog with full focus lifecycle', async ({
    page,
  }) => {
    for (const locale of ['en', 'es', 'it'] as const) {
      for (const width of [390, 1440]) {
        await test.step(`${locale} at ${width}px`, async () => {
          await page.setViewportSize({ width, height: 900 });
          await page.goto(`/${locale}/tours/${slug}`);
          await expect(page.locator('main#main-content')).toBeVisible();
          const trigger = page.locator('main#main-content [data-testid="wishlist-button"]').first();
          await expect(trigger).toBeVisible();
          await expect(trigger).toBeEnabled();
          await trigger.focus();
          await trigger.click();
          const dialog = page.getByRole('dialog', { name: promptTitle[locale], exact: true });
          await expect(dialog).toBeVisible();
          // Focus moves inside the dialog; Escape closes and the actual
          // triggering header control regains focus.
          await page.keyboard.press('Escape');
          await expect(dialog).toHaveCount(0);
          await expect(trigger).toBeFocused();
        });
      }
    }
  });

  test('US5 review page failure preserves the readable tour', async ({ page }) => {
    await page.route('**/api/public/tours/*/reviews*', async (route) => {
      await route.fulfill({ status: 500, contentType: 'application/json', body: '{}' });
    });
    await page.goto(`/en/tours/${slug}`);
    await expect(page.locator('main#main-content')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByText('Failed to load reviews. Please try again later.')).toBeVisible();
  });

  test('US5 media failure preserves the readable tour', async ({ page }) => {
    await page.route('**/*.{png,jpg,jpeg,webp,avif}', async (route) => {
      await route.abort();
    });
    await page.goto(`/en/tours/${slug}`);
    await expect(page.locator('main#main-content')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Tour sections' })).toBeVisible();
  });
});
