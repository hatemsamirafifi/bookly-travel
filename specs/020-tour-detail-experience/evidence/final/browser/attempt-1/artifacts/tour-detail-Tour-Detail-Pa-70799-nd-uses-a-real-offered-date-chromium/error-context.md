# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> mobile booking action is keyboard reachable and uses a real offered date
- Location: tests/e2e/tour-detail.spec.ts:475:7

# Error details

```
Error: expect(received).toMatch(expected)

Expected pattern: /^\/en\/booking\?tour=hidden-gems-rome-walking-tour&participants=1&date=\d{4}-\d{2}-\d{2}$/
Received string:  "/en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17"
```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - banner [ref=e2]:
    - link "Skip to main content" [ref=e3] [cursor=pointer]:
      - /url: "#main-content"
    - generic [ref=e4]:
      - link "Bookly" [ref=e5] [cursor=pointer]:
        - /url: /en
      - generic [ref=e6]:
        - button "Search tours" [ref=e7]:
          - img [ref=e8]
        - link "Sign In" [ref=e11] [cursor=pointer]:
          - /url: /en/auth/login
          - img [ref=e12]
        - button "Open menu" [ref=e15]:
          - img [ref=e16]
  - main [ref=e17]:
    - generic [ref=e18]:
      - generic [ref=e19]:
        - navigation "Breadcrumbs" [ref=e20]:
          - list [ref=e21]:
            - listitem [ref=e22]:
              - link "Home" [ref=e23] [cursor=pointer]:
                - /url: /en
            - listitem [ref=e24]:
              - generic [ref=e25]: /
              - link "Walking Tours" [ref=e26] [cursor=pointer]:
                - /url: /en/categories/walking
            - listitem [ref=e27]:
              - generic [ref=e28]: /
              - generic [ref=e29]: Hidden Gems of Rome Walking Tour
        - generic [ref=e30]:
          - heading "Hidden Gems of Rome Walking Tour" [level=1] [ref=e31]
          - button "Save to wishlist" [ref=e32]:
            - generic [ref=e33]: ♡
        - generic [ref=e35]:
          - img [ref=e36]
          - text: Rome, Italy
        - generic [ref=e39]:
          - 'generic "Rating: 4.5 out of 5" [ref=e40]':
            - img [ref=e41]
            - generic [ref=e43]: "4.5"
            - generic [ref=e44]: "Rating: 4.5 out of 5"
          - link "(2 Reviews)" [ref=e45] [cursor=pointer]:
            - /url: "#reviews"
      - generic [ref=e46]:
        - generic [ref=e47]:
          - button "View image 1" [ref=e48]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e49]
          - button "View image 2" [ref=e50]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e51]
          - button "View image 3" [ref=e52]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e53]
          - button "View image 4" [ref=e54]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e55]
          - button "View image 5" [ref=e56]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e57]
        - button "Previous image" [ref=e58]:
          - img [ref=e59]
        - button "Next image" [ref=e61]:
          - img [ref=e62]
        - paragraph [ref=e64]: 1 of 5
      - navigation "Tour sections" [ref=e65]:
        - generic [ref=e66]:
          - link "About This Tour" [ref=e67] [cursor=pointer]:
            - /url: "#overview"
          - link "Tour facts" [ref=e68] [cursor=pointer]:
            - /url: "#facts"
          - link "Highlights" [ref=e69] [cursor=pointer]:
            - /url: "#highlights"
          - link "What's Included" [ref=e70] [cursor=pointer]:
            - /url: "#included"
          - link "What's Excluded" [ref=e71] [cursor=pointer]:
            - /url: "#excluded"
          - link "Meeting Point" [ref=e72] [cursor=pointer]:
            - /url: "#meeting"
          - link "Cancellation Policy" [ref=e73] [cursor=pointer]:
            - /url: "#cancellation"
          - link "Reviews" [ref=e74] [cursor=pointer]:
            - /url: "#reviews"
          - link "More experiences to explore" [ref=e75] [cursor=pointer]:
            - /url: "#related"
      - generic [ref=e76]:
        - article [ref=e77]:
          - generic [ref=e78]:
            - heading "About This Tour" [level=2] [ref=e79]
            - generic [ref=e80]: Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.
          - generic [ref=e81]:
            - heading "Tour facts" [level=2] [ref=e82]
            - generic [ref=e83]:
              - generic [ref=e84]:
                - term [ref=e85]: Duration
                - definition [ref=e86]: 3 hours
              - generic [ref=e87]:
                - term [ref=e88]: Group size
                - definition [ref=e89]: 1–20 travelers
          - generic [ref=e90]:
            - heading "Highlights" [level=2] [ref=e91]
            - list [ref=e92]:
              - listitem [ref=e93]:
                - img [ref=e94]
                - text: Hidden courtyards
              - listitem [ref=e96]:
                - img [ref=e97]
                - text: Local artisan shops
              - listitem [ref=e99]:
                - img [ref=e100]
                - text: Ancient Roman stories
              - listitem [ref=e102]:
                - img [ref=e103]
                - text: Off the beaten path
          - generic [ref=e105]:
            - heading "What's Included" [level=3] [ref=e109]:
              - button "What's Included" [ref=e110]:
                - text: What's Included
                - img [ref=e111]
            - heading "What's Excluded" [level=3] [ref=e116]:
              - button "What's Excluded" [ref=e117]:
                - text: What's Excluded
                - img [ref=e118]
          - generic [ref=e120]:
            - heading "Meeting Point" [level=2] [ref=e121]
            - paragraph [ref=e122]:
              - img [ref=e123]
              - text: Colosseum main entrance
          - generic [ref=e126]:
            - heading "Cancellation Policy" [level=2] [ref=e127]
            - paragraph [ref=e128]: Free cancellation up to 24 hours before the tour.
          - region "Reviews" [ref=e129]:
            - heading "Reviews" [level=2] [ref=e130]
            - generic [ref=e131]:
              - heading "Reviews" [level=3] [ref=e132]
              - status [ref=e133]: Loading...
        - complementary:
          - generic [ref=e144]:
            - paragraph [ref=e145]:
              - generic [ref=e146]: €45.00 / person
              - generic [ref=e147]: 10/17/2026 · 1 · €45.00
            - generic [ref=e148]:
              - link "Continue to booking" [ref=e149] [cursor=pointer]:
                - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
              - button "Edit booking selections" [ref=e150]
      - region "More experiences to explore" [ref=e151]:
        - heading "More experiences to explore" [level=2] [ref=e152]
        - generic [ref=e153]:
          - generic [ref=e154]:
            - 'link "Venice Hidden Canals Walking Tour Next: Oct 17, 2026 Venice, Italy 2.5 hours Venice Hidden Canals Walking Tour Walking Tours €55.00" [ref=e155] [cursor=pointer]':
              - /url: /en/tours/venice-walking-tour-hidden-canals
              - generic [ref=e156]:
                - img "Venice Hidden Canals Walking Tour" [ref=e157]
                - generic [ref=e158]: "Next: Oct 17, 2026"
              - generic [ref=e159]:
                - generic [ref=e160]:
                  - generic [ref=e161]: Venice, Italy
                  - generic [ref=e162]: ·
                  - generic [ref=e163]: 2.5 hours
                - heading "Venice Hidden Canals Walking Tour" [level=2] [ref=e164]
                - generic [ref=e165]:
                  - generic [ref=e166]: Walking Tours
                  - generic [ref=e167]: €55.00
            - button "Save to wishlist" [ref=e169]:
              - generic [ref=e170]: ♡
          - generic [ref=e171]:
            - 'link "Barcelona Gaudí Architecture Masterpieces Tour Next: Oct 17, 2026 Barcelona, Spain 4 hours Barcelona Gaudí Architecture Masterpieces Tour Walking Tours €65.00" [ref=e172] [cursor=pointer]':
              - /url: /en/tours/barcelona-gaudi-architecture-tour
              - generic [ref=e173]:
                - img "Barcelona Gaudí Architecture Masterpieces Tour" [ref=e174]
                - generic [ref=e175]: "Next: Oct 17, 2026"
              - generic [ref=e176]:
                - generic [ref=e177]:
                  - generic [ref=e178]: Barcelona, Spain
                  - generic [ref=e179]: ·
                  - generic [ref=e180]: 4 hours
                - heading "Barcelona Gaudí Architecture Masterpieces Tour" [level=2] [ref=e181]
                - generic [ref=e182]:
                  - generic [ref=e183]: Walking Tours
                  - generic [ref=e184]: €65.00
            - button "Save to wishlist" [ref=e186]:
              - generic [ref=e187]: ♡
          - generic [ref=e188]:
            - 'link "Montmartre Artists Quarter Walking Tour Next: Oct 17, 2026 Paris, France 2.5 hours Montmartre Artists Quarter Walking Tour Walking Tours €49.00" [ref=e189] [cursor=pointer]':
              - /url: /en/tours/paris-montmartre-artists-quarter
              - generic [ref=e190]:
                - img "Montmartre Artists Quarter Walking Tour" [ref=e191]
                - generic [ref=e192]: "Next: Oct 17, 2026"
              - generic [ref=e193]:
                - generic [ref=e194]:
                  - generic [ref=e195]: Paris, France
                  - generic [ref=e196]: ·
                  - generic [ref=e197]: 2.5 hours
                - heading "Montmartre Artists Quarter Walking Tour" [level=2] [ref=e198]
                - generic [ref=e199]:
                  - generic [ref=e200]: Walking Tours
                  - generic [ref=e201]: €49.00
            - button "Save to wishlist" [ref=e203]:
              - generic [ref=e204]: ♡
  - contentinfo [ref=e205]:
    - generic [ref=e206]:
      - generic [ref=e207]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e210]
        - generic [ref=e211]:
          - paragraph [ref=e212]: Explore
          - list [ref=e213]:
            - listitem [ref=e214]:
              - link "Search tours" [ref=e215] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e216]:
              - link "Categories" [ref=e217] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e218]:
              - link "Destinations" [ref=e219] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e220]:
          - paragraph [ref=e221]: Company
          - list [ref=e222]:
            - listitem [ref=e223]:
              - link "Privacy policy" [ref=e224] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e225]:
              - link "Terms of service" [ref=e226] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e227]:
        - generic [ref=e228]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e230]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e231]:
      - generic [ref=e232]: We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies.
      - generic [ref=e233]:
        - button "Reject" [ref=e234]
        - button "Accept" [ref=e235]
  - alert [ref=e236]
```

# Test source

```ts
  381 |   test('shows the reviews section only when the API reports genuine reviews', async ({ page, request }) => {
  382 |     const response = await request.get('/api/public/tours/hidden-gems-rome-walking-tour?locale=en');
  383 |     expect(response.ok()).toBeTruthy();
  384 |     const { data } = await response.json();
  385 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  386 | 
  387 |     const reviewsHeading = page.getByRole('heading', { name: /Reviews/i });
  388 |     const reviewsLink = page.getByRole('navigation', { name: 'Tour sections' }).getByRole('link', { name: 'Reviews' });
  389 |     if (data.reviews.count > 0) {
  390 |       await expect(reviewsHeading).toBeVisible();
  391 |       await expect(reviewsLink).toBeVisible();
  392 |     } else {
  393 |       await expect(reviewsHeading).toHaveCount(0);
  394 |       await expect(reviewsLink).toHaveCount(0);
  395 |     }
  396 |   });
  397 | 
  398 |   test('about section displays description', async ({ page }) => {
  399 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  400 | 
  401 |     const aboutHeading = page.getByRole('heading', { name: 'About This Tour' });
  402 |     await expect(aboutHeading).toBeVisible({ timeout: 10000 });
  403 |   });
  404 | 
  405 |   test('tour detail has proper SEO metadata', async ({ page }) => {
  406 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  407 | 
  408 |     await expect(page).toHaveTitle(/Bookly/);
  409 | 
  410 |     // Check canonical link
  411 |     const canonical = page.locator('link[rel="canonical"]');
  412 |     if (await canonical.isVisible().catch(() => false)) {
  413 |       const href = await canonical.getAttribute('href');
  414 |       expect(href).toContain('/tours/');
  415 |     }
  416 |   });
  417 | 
  418 |   // ─── Responsive Viewport Tests (FR-032, T092) ───
  419 | 
  420 |   const viewports = [
  421 |     { name: 'mobile', width: 375, height: 667 },
  422 |     { name: 'tablet', width: 768, height: 1024 },
  423 |     { name: 'desktop', width: 1280, height: 800 },
  424 |   ];
  425 | 
  426 |   for (const vp of viewports) {
  427 |     test(`tour detail page adapts to ${vp.name} viewport (${vp.width}px) without horizontal scroll`, async ({ page }) => {
  428 |       await page.setViewportSize({ width: vp.width, height: vp.height });
  429 |       await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  430 | 
  431 |       await expect(page.locator('main')).toBeVisible({ timeout: 10000 });
  432 | 
  433 |       const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
  434 |       const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
  435 |       expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  436 |     });
  437 |   }
  438 | 
  439 |   test('tour detail content stacks vertically on mobile', async ({ page }) => {
  440 |     await page.setViewportSize({ width: 375, height: 667 });
  441 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  442 | 
  443 |     await expect(page.locator('main')).toBeVisible({ timeout: 10000 });
  444 | 
  445 |     // Content should not overflow horizontally
  446 |     const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
  447 |     const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
  448 |     expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  449 |   });
  450 | 
  451 |   test('structured data matches the visible tour and does not invent reviews', async ({ page, request }) => {
  452 |     const slug = 'hidden-gems-rome-walking-tour';
  453 |     const response = await request.get(`/api/public/tours/${slug}?locale=en`);
  454 |     expect(response.ok()).toBeTruthy();
  455 |     const { data } = await response.json();
  456 |     await page.goto(`/en/tours/${slug}`);
  457 |     const script = page.locator('script[type="application/ld+json"]').first();
  458 |     const schema = JSON.parse(await script.textContent() ?? '{}');
  459 |     expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
  460 |     expect(schema.name).toBe(await page.getByRole('heading', { level: 1 }).textContent());
  461 |     expect(schema['@context'].name['@language']).toBe('en');
  462 |     expect(schema['@context'].description['@language']).toBe('en');
  463 |     expect(schema.mainEntityOfPage.inLanguage).toBe('en');
  464 |     expect(schema.inLanguage).toBeUndefined();
  465 |     expect(schema.description).toContain('Colosseum main entrance');
  466 |     expect(schema.image).toEqual(expect.arrayContaining([expect.stringMatching(/^https?:\/\//)]));
  467 |     if (data.reviews.count > 0 && data.reviews.average_rating > 0) {
  468 |       expect(schema.aggregateRating.reviewCount).toBe(data.reviews.count);
  469 |       expect(schema.aggregateRating.ratingValue).toBe(data.reviews.average_rating.toFixed(1));
  470 |     } else {
  471 |       expect(schema.aggregateRating).toBeUndefined();
  472 |     }
  473 |   });
  474 | 
  475 |   test('mobile booking action is keyboard reachable and uses a real offered date', async ({ page }) => {
  476 |     await page.setViewportSize({ width: 390, height: 844 });
  477 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  478 |     const action = page.getByRole('link', { name: 'Continue to booking' });
  479 |     await expect(action).toBeVisible();
  480 |     const href = await action.getAttribute('href');
> 481 |     expect(href).toMatch(/^\/en\/booking\?tour=hidden-gems-rome-walking-tour&participants=1&date=\d{4}-\d{2}-\d{2}$/);
      |                  ^ Error: expect(received).toMatch(expected)
  482 |     await action.focus();
  483 |     await expect(action).toBeFocused();
  484 |   });
  485 | 
  486 |   for (const [locale, bookNow, selectDate] of [
  487 |     ['en', 'Book Now', 'Select a Date'],
  488 |     ['es', 'Reservar Ahora', 'Seleccionar Fecha'],
  489 |     ['it', 'Prenota Ora', 'Seleziona Data'],
  490 |   ]) {
  491 |     test(`${locale} detail keeps localized booking controls and a real offered date`, async ({ page, request }) => {
  492 |       const slug = 'hidden-gems-rome-walking-tour';
  493 |       const response = await request.get(`/api/public/tours/${slug}?locale=${locale}`);
  494 |       expect(response.ok()).toBeTruthy();
  495 |       const { data } = await response.json();
  496 |       await page.goto(`/${locale}/tours/${slug}`);
  497 |       await expect(page.getByRole('heading', { level: 1 })).toHaveText(data.title);
  498 |       await expect(page.getByRole('heading', { name: selectDate })).toBeVisible();
  499 |       const action = page.getByRole('link', { name: bookNow }).first();
  500 |       await expect(action).toBeVisible();
  501 |       const href = new URL((await action.getAttribute('href'))!, 'http://bookly.test');
  502 |       expect(href.searchParams.get('date')).toBe(data.availability.next_available_date);
  503 |       expect(data.availability.available_dates).toContain(href.searchParams.get('date'));
  504 |     });
  505 |   }
  506 | 
  507 |   // Spec 019 US4: integrated media/difficulty/guide reads from the REAL
  508 |   // backend through a real isolated Next SSR process (never fabricated JSON;
  509 |   // browser route mocking cannot intercept SSR). Writes touch only an owned
  510 |   // disposable fixture on the seeded published tour and restore it in
  511 |   // `finally` via an actual partner PUT. Auth reuses the existing setup
  512 |   // storageState file in a separate browser context; no token is printed.
  513 |   test('US4 public SSR shows real cover-first gallery with EN alt lang=en on ES/IT pages (seeded tour, restored)', async ({
  514 |     page,
  515 |     request,
  516 |     browser,
  517 |     baseURL,
  518 |   }) => {
  519 |     test.setTimeout(180000);
  520 |     const slug = 'hidden-gems-rome-walking-tour';
  521 |     const authedContext = await browser.newContext({ baseURL, storageState: 'tests/.auth/partner.json' });
  522 |     const authedPage = await authedContext.newPage();
  523 |     let tourId = 0;
  524 |     let snapshot: {
  525 |       media: Array<{ url: string; is_cover: boolean; alt_text?: string | null }>;
  526 |       coverUrl: string | null;
  527 |       difficulty: string | null;
  528 |       guides: string[];
  529 |     } | null = null;
  530 |     try {
  531 |       await authedPage.goto('/en/partner/tours');
  532 |       const boot = await request.get(`/api/public/tours/${slug}?locale=en`);
  533 |       expect(boot.ok()).toBeTruthy();
  534 |       const bootData = ((await boot.json()).data) as TourDetail;
  535 |       tourId = bootData.id;
  536 |       // Legacy seeded tours carry no invented difficulty: absent reads null.
  537 |       if (bootData.difficulty_level === null || bootData.difficulty_level === undefined) {
  538 |         expect(bootData.difficulty_level ?? null).toBeNull();
  539 |       }
  540 |       const owned = await apiGet(authedPage, `/api/partner/tours/${tourId}`);
  541 |       if (owned.status !== 200) {
  542 |         throw new Error(
  543 |           `Spec019 US4 seed prerequisite: expected seeded partner@bookly.test to own published tour ${slug} ` +
  544 |             `(partner GET /api/partner/tours/${tourId} returned ${owned.status}). ` +
  545 |             'Root must prepare ownership/seeding instead of inventing state.'
  546 |         );
  547 |       }
  548 |       snapshot = {
  549 |         media: ownedMedia(owned.json).map((row) => ({
  550 |           url: row.url,
  551 |           is_cover: row.is_cover,
  552 |           ...(row.alt_text === undefined ? {} : { alt_text: row.alt_text }),
  553 |         })),
  554 |         coverUrl: ownedCoverUrl(owned.json),
  555 |         difficulty: ownedDifficulty(owned.json),
  556 |         guides: ownedGuideLanguages(owned.json),
  557 |       };
  558 | 
  559 |       const stamp = Date.now().toString(36);
  560 |       const coverUrl = `http://cdn.bookly.test/tours/rome-cover.jpg?us4=${stamp}-cover`;
  561 |       const secondUrl = `http://cdn.bookly.test/tours/rome-gallery-1.jpg?us4=${stamp}-second`;
  562 |       const thirdUrl = `http://cdn.bookly.test/tours/rome-gallery-2.jpg?us4=${stamp}-third`;
  563 |       const coverAltEn = 'US4 harbor sunrise Caf\u00e9';
  564 |       const secondAltEn = 'US4 old street';
  565 |       const fixture = await apiPut(authedPage, `/api/partner/tours/${tourId}`, {
  566 |         media: [
  567 |           { url: coverUrl, is_cover: true, alt_text: coverAltEn },
  568 |           { url: secondUrl, is_cover: false, alt_text: secondAltEn },
  569 |           { url: thirdUrl, is_cover: false, alt_text: null },
  570 |         ],
  571 |         cover_image_url: coverUrl,
  572 |         guide_languages: ['en', 'de'],
  573 |       });
  574 |       if (fixture.status !== 200) {
  575 |         throw new Error(
  576 |           `Spec019 US4 seed prerequisite: fixture PUT on owned seeded tour ${slug} returned ${fixture.status}. ` +
  577 |             'Root must prepare a writable owned fixture instead of inventing state.'
  578 |         );
  579 |       }
  580 | 
  581 |       const scenarios = [
```