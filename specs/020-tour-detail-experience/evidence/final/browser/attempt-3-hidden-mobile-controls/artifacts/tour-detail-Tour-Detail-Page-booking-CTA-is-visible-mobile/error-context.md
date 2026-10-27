# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> booking CTA is visible
- Location: tests/e2e/tour-detail.spec.ts:354:7

# Error details

```
Error: expect(received).toBeTruthy()

Received: false
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
              - generic [ref=e147]: 10/18/2026 · 1 · €45.00
            - generic [ref=e148]:
              - link "Continue to booking" [ref=e149] [cursor=pointer]:
                - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-18
              - button "Edit booking selections" [ref=e150]
      - region "More experiences to explore" [ref=e151]:
        - heading "More experiences to explore" [level=2] [ref=e152]
        - generic [ref=e153]:
          - generic [ref=e154]:
            - 'link "Venice Hidden Canals Walking Tour Next: Oct 18, 2026 Venice, Italy 2.5 hours Venice Hidden Canals Walking Tour Walking Tours €55.00" [ref=e155] [cursor=pointer]':
              - /url: /en/tours/venice-walking-tour-hidden-canals
              - generic [ref=e156]:
                - img "Venice Hidden Canals Walking Tour" [ref=e157]
                - generic [ref=e158]: "Next: Oct 18, 2026"
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
            - 'link "Barcelona Gaudí Architecture Masterpieces Tour Next: Oct 18, 2026 Barcelona, Spain 4 hours Barcelona Gaudí Architecture Masterpieces Tour Walking Tours €65.00" [ref=e172] [cursor=pointer]':
              - /url: /en/tours/barcelona-gaudi-architecture-tour
              - generic [ref=e173]:
                - img "Barcelona Gaudí Architecture Masterpieces Tour" [ref=e174]
                - generic [ref=e175]: "Next: Oct 18, 2026"
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
            - 'link "Montmartre Artists Quarter Walking Tour Next: Oct 18, 2026 Paris, France 2.5 hours Montmartre Artists Quarter Walking Tour Walking Tours €49.00" [ref=e189] [cursor=pointer]':
              - /url: /en/tours/paris-montmartre-artists-quarter
              - generic [ref=e190]:
                - img "Montmartre Artists Quarter Walking Tour" [ref=e191]
                - generic [ref=e192]: "Next: Oct 18, 2026"
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
  - complementary "Cookie consent"
  - alert [ref=e231]
```

# Test source

```ts
  267 |         expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
  268 |         expect(schema['@context'].name['@language']).toBe(scenario.locale);
  269 |         expect(schema.mainEntityOfPage.inLanguage).toBe(scenario.locale);
  270 |         expect(schema.inLanguage).toBeUndefined();
  271 |       }
  272 |     } finally {
  273 |       await harness.stop();
  274 |     }
  275 |   });
  276 |   test('tour detail page loads with all sections', async ({ page }) => {
  277 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  278 | 
  279 |     // Page should load
  280 |     await expect(page.locator('main')).toBeVisible();
  281 | 
  282 |     // Title should be present
  283 |     await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  284 |   });
  285 | 
  286 |   test('image gallery is visible', async ({ page }) => {
  287 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  288 | 
  289 |     const gallery = page.locator('.aspect-\\[16\\/10\\]').first();
  290 |     await expect(gallery).toBeVisible({ timeout: 10000 });
  291 |   });
  292 | 
  293 |   test('image gallery rail controls move the counter with no thumbnail navigation', async ({ page }) => {
  294 |     // Mobile rail: the counter and rail arrows are the <1024px controls.
  295 |     await page.setViewportSize({ width: 390, height: 844 });
  296 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  297 | 
  298 |     // Canonical gallery: one photo DOM (scroll-snap rail below 1024px,
  299 |     // mosaic at >=1024px); thumbnails no longer exist.
  300 |     const gallery = page.getByTestId('tour-gallery');
  301 |     await expect(gallery).toBeVisible({ timeout: 10000 });
  302 |     await expect(gallery.getByRole('list', { name: /thumbnail/i })).toHaveCount(0);
  303 |     const counter = gallery.getByText(/^\d+ of \d+$/);
  304 |     await expect(counter).toBeVisible();
  305 |     const initial = await counter.textContent();
  306 |     const total = Number(initial?.match(/of (\d+)$/)?.[1] ?? '0');
  307 |     expect(total).toBeGreaterThan(0);
  308 |     if (total > 1) {
  309 |       await gallery.getByRole('button', { name: 'Next image' }).click();
  310 |       await expect(counter).toHaveText(`2 of ${total}`);
  311 |       await gallery.getByRole('button', { name: 'Previous image' }).click();
  312 |       await expect(counter).toHaveText(`1 of ${total}`);
  313 |     } else {
  314 |       await expect(gallery.getByRole('button', { name: 'Next image' })).toHaveCount(0);
  315 |     }
  316 |   });
  317 | 
  318 |   test('lightbox opens from a keyboard-focusable tile and restores focus on Escape', async ({ page }) => {
  319 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  320 | 
  321 |     const trigger = page.getByRole('button', { name: 'View image 1' });
  322 |     await expect(trigger).toBeVisible();
  323 |     await trigger.focus();
  324 |     await page.keyboard.press('Enter');
  325 |     await expect(page.getByRole('dialog', { name: 'Image lightbox' })).toBeVisible();
  326 |     await expect(page.getByRole('button', { name: 'Close lightbox' })).toBeFocused();
  327 |     await page.keyboard.press('Escape');
  328 |     await expect(page.getByRole('dialog', { name: 'Image lightbox' })).not.toBeVisible();
  329 |     await expect(trigger).toBeFocused();
  330 |   });
  331 | 
  332 |   test('section navigation links only to rendered sections', async ({ page }) => {
  333 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  334 |     const nav = page.getByRole('navigation', { name: 'Tour sections' });
  335 |     await expect(nav).toBeVisible();
  336 |     const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
  337 |     expect(hrefs.length).toBeGreaterThan(0);
  338 |     for (const href of hrefs) {
  339 |       expect(href).toMatch(/^#[a-z-]+$/);
  340 |       await expect(page.locator(href!)).toHaveCount(1);
  341 |     }
  342 |   });
  343 | 
  344 |   test('availability calendar shows available dates', async ({ page }) => {
  345 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  346 | 
  347 |     if ((page.viewportSize()?.width ?? 0) < 1024) {
  348 |       await page.getByTestId('mobile-booking-bar').getByRole('button').click();
  349 |     }
  350 |     const calendar = page.getByRole('combobox', { name: 'Select a Date', exact: true });
  351 |     await expect(calendar).toBeVisible({ timeout: 10000 });
  352 |   });
  353 | 
  354 |   test('booking CTA is visible', async ({ page }) => {
  355 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  356 | 
  357 |     const bookNow = page.getByRole('button', { name: /Book Now/i });
  358 |     const bookNowLink = page.getByRole('link', { name: /Book Now/i });
  359 | 
  360 |     const hasCTA = (await bookNow.isVisible().catch(() => false))
  361 |       || (await bookNowLink.isVisible().catch(() => false));
  362 | 
  363 |     // May show "Currently Unavailable" instead
  364 |     const unavailable = page.getByText('Currently Unavailable');
  365 |     const hasUnavailable = await unavailable.isVisible().catch(() => false);
  366 | 
> 367 |     expect(hasCTA || hasUnavailable).toBeTruthy();
      |                                      ^ Error: expect(received).toBeTruthy()
  368 |   });
  369 | 
  370 |   test('participant selector increments and decrements', async ({ page }) => {
  371 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  372 | 
  373 |     const increaseBtn = page.getByRole('button', { name: 'Increase participants' });
  374 |     const decreaseBtn = page.getByRole('button', { name: 'Decrease participants' });
  375 | 
  376 |     if (await increaseBtn.isVisible()) {
  377 |       await increaseBtn.click();
  378 |       // Count should change - exact value depends on tour's group size
  379 |       await expect(page.getByText(/allowed/)).toBeVisible();
  380 |       await expect(decreaseBtn).toBeVisible();
  381 |     }
  382 |   });
  383 | 
  384 |   test('shows 404 page for non-existent tour', async ({ page }) => {
  385 |     await page.goto('/en/tours/this-tour-does-not-exist-xyz');
  386 | 
  387 |     // Next.js default not-found behavior or API error
  388 |     await expect(page.locator('main')).toBeVisible();
  389 |   });
  390 | 
  391 |   test('shows the reviews section only when the API reports genuine reviews', async ({ page, request }) => {
  392 |     const response = await request.get('/api/public/tours/hidden-gems-rome-walking-tour?locale=en');
  393 |     expect(response.ok()).toBeTruthy();
  394 |     const { data } = await response.json();
  395 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  396 | 
  397 |     const reviewsHeading = page.getByRole('heading', { level: 2, name: 'Reviews', exact: true });
  398 |     const reviewsLink = page.getByRole('navigation', { name: 'Tour sections' }).getByRole('link', { name: 'Reviews' });
  399 |     if (data.reviews.count > 0) {
  400 |       await expect(reviewsHeading).toBeVisible();
  401 |       await expect(reviewsLink).toBeVisible();
  402 |     } else {
  403 |       await expect(reviewsHeading).toHaveCount(0);
  404 |       await expect(reviewsLink).toHaveCount(0);
  405 |     }
  406 |   });
  407 | 
  408 |   test('about section displays description', async ({ page }) => {
  409 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  410 | 
  411 |     const aboutHeading = page.getByRole('heading', { name: 'About This Tour' });
  412 |     await expect(aboutHeading).toBeVisible({ timeout: 10000 });
  413 |   });
  414 | 
  415 |   test('tour detail has proper SEO metadata', async ({ page }) => {
  416 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  417 | 
  418 |     await expect(page).toHaveTitle(/Bookly/);
  419 | 
  420 |     // Check canonical link
  421 |     const canonical = page.locator('link[rel="canonical"]');
  422 |     if (await canonical.isVisible().catch(() => false)) {
  423 |       const href = await canonical.getAttribute('href');
  424 |       expect(href).toContain('/tours/');
  425 |     }
  426 |   });
  427 | 
  428 |   // ─── Responsive Viewport Tests (FR-032, T092) ───
  429 | 
  430 |   const viewports = [
  431 |     { name: 'mobile', width: 375, height: 667 },
  432 |     { name: 'tablet', width: 768, height: 1024 },
  433 |     { name: 'desktop', width: 1280, height: 800 },
  434 |   ];
  435 | 
  436 |   for (const vp of viewports) {
  437 |     test(`tour detail page adapts to ${vp.name} viewport (${vp.width}px) without horizontal scroll`, async ({ page }) => {
  438 |       await page.setViewportSize({ width: vp.width, height: vp.height });
  439 |       await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  440 | 
  441 |       await expect(page.locator('main')).toBeVisible({ timeout: 10000 });
  442 | 
  443 |       const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
  444 |       const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
  445 |       expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  446 |     });
  447 |   }
  448 | 
  449 |   test('tour detail content stacks vertically on mobile', async ({ page }) => {
  450 |     await page.setViewportSize({ width: 375, height: 667 });
  451 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  452 | 
  453 |     await expect(page.locator('main')).toBeVisible({ timeout: 10000 });
  454 | 
  455 |     // Content should not overflow horizontally
  456 |     const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
  457 |     const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
  458 |     expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  459 |   });
  460 | 
  461 |   test('structured data matches the visible tour and does not invent reviews', async ({ page, request }) => {
  462 |     const slug = 'hidden-gems-rome-walking-tour';
  463 |     const response = await request.get(`/api/public/tours/${slug}?locale=en`);
  464 |     expect(response.ok()).toBeTruthy();
  465 |     const { data } = await response.json();
  466 |     await page.goto(`/en/tours/${slug}`);
  467 |     const script = page.locator('script[type="application/ld+json"]').first();
```