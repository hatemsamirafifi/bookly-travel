# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> shows the reviews section only when the API reports genuine reviews
- Location: tests/e2e/tour-detail.spec.ts:381:7

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('heading', { name: /Reviews/i })
Expected: visible
Error: strict mode violation: getByRole('heading', { name: /Reviews/i }) resolved to 2 elements:
    1) <h2 id="reviews-title" class="mb-3 text-xl font-semibold text-bookly-navy">Reviews</h2> aka locator('#reviews-title')
    2) <h3 class="mb-3 text-sm font-semibold text-gray-700">Reviews</h3> aka getByRole('region', { name: 'Reviews' }).locator('h3')

Call log:
  - Expect "toBeVisible" with timeout 20000ms
  - waiting for getByRole('heading', { name: /Reviews/i })

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
      - navigation "Main navigation" [ref=e6]:
        - link "Home" [ref=e7] [cursor=pointer]:
          - /url: /en
        - link "Search tours" [ref=e8] [cursor=pointer]:
          - /url: /en/search
        - link "Categories" [ref=e9] [cursor=pointer]:
          - /url: /en/categories
        - link "Destinations" [ref=e10] [cursor=pointer]:
          - /url: /en/destinations
      - generic [ref=e11]:
        - button "Search tours" [ref=e12]:
          - img [ref=e13]
        - combobox "Switch language" [ref=e18]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
        - link "Sign In" [ref=e19] [cursor=pointer]:
          - /url: /en/auth/login
          - generic [ref=e20]: Sign In
        - link "Sign Up" [ref=e21] [cursor=pointer]:
          - /url: /en/auth/register
  - main [ref=e22]:
    - generic [ref=e23]:
      - generic [ref=e24]:
        - navigation "Breadcrumbs" [ref=e25]:
          - list [ref=e26]:
            - listitem [ref=e27]:
              - link "Home" [ref=e28] [cursor=pointer]:
                - /url: /en
            - listitem [ref=e29]:
              - generic [ref=e30]: /
              - link "Walking Tours" [ref=e31] [cursor=pointer]:
                - /url: /en/categories/walking
            - listitem [ref=e32]:
              - generic [ref=e33]: /
              - generic [ref=e34]: Hidden Gems of Rome Walking Tour
        - generic [ref=e35]:
          - heading "Hidden Gems of Rome Walking Tour" [level=1] [ref=e36]
          - button "Save to wishlist" [ref=e37]:
            - generic [ref=e38]: ♡
        - generic [ref=e40]:
          - img [ref=e41]
          - text: Rome, Italy
        - generic [ref=e44]:
          - 'generic "Rating: 4.5 out of 5" [ref=e45]':
            - img [ref=e46]
            - generic [ref=e48]: "4.5"
            - generic [ref=e49]: "Rating: 4.5 out of 5"
          - link "(2 Reviews)" [ref=e50] [cursor=pointer]:
            - /url: "#reviews"
      - generic [ref=e51]:
        - generic [ref=e52]:
          - button "View image 1" [ref=e53]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e54]
          - button "View image 2" [ref=e55]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e56]
          - button "View image 3" [ref=e57]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e58]
          - button "View image 4" [ref=e59]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e60]
          - button "View image 5" [ref=e61]:
            - img "Hidden Gems of Rome Walking Tour" [ref=e62]
        - button "See all 5 photos" [ref=e63]
      - navigation "Tour sections" [ref=e64]:
        - generic [ref=e65]:
          - link "About This Tour" [ref=e66] [cursor=pointer]:
            - /url: "#overview"
          - link "Tour facts" [ref=e67] [cursor=pointer]:
            - /url: "#facts"
          - link "Highlights" [ref=e68] [cursor=pointer]:
            - /url: "#highlights"
          - link "What's Included" [ref=e69] [cursor=pointer]:
            - /url: "#included"
          - link "What's Excluded" [ref=e70] [cursor=pointer]:
            - /url: "#excluded"
          - link "Meeting Point" [ref=e71] [cursor=pointer]:
            - /url: "#meeting"
          - link "Cancellation Policy" [ref=e72] [cursor=pointer]:
            - /url: "#cancellation"
          - link "Reviews" [ref=e73] [cursor=pointer]:
            - /url: "#reviews"
          - link "More experiences to explore" [ref=e74] [cursor=pointer]:
            - /url: "#related"
      - generic [ref=e75]:
        - article [ref=e76]:
          - generic [ref=e77]:
            - heading "About This Tour" [level=2] [ref=e78]
            - generic [ref=e79]: Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.
          - generic [ref=e80]:
            - heading "Tour facts" [level=2] [ref=e81]
            - generic [ref=e82]:
              - generic [ref=e83]:
                - term [ref=e84]: Duration
                - definition [ref=e85]: 3 hours
              - generic [ref=e86]:
                - term [ref=e87]: Group size
                - definition [ref=e88]: 1–20 travelers
          - generic [ref=e89]:
            - heading "Highlights" [level=2] [ref=e90]
            - list [ref=e91]:
              - listitem [ref=e92]:
                - img [ref=e93]
                - text: Hidden courtyards
              - listitem [ref=e95]:
                - img [ref=e96]
                - text: Local artisan shops
              - listitem [ref=e98]:
                - img [ref=e99]
                - text: Ancient Roman stories
              - listitem [ref=e101]:
                - img [ref=e102]
                - text: Off the beaten path
          - generic [ref=e104]:
            - heading "What's Included" [level=3] [ref=e108]:
              - button "What's Included" [ref=e109]:
                - text: What's Included
                - img [ref=e110]
            - heading "What's Excluded" [level=3] [ref=e115]:
              - button "What's Excluded" [ref=e116]:
                - text: What's Excluded
                - img [ref=e117]
          - generic [ref=e119]:
            - heading "Meeting Point" [level=2] [ref=e120]
            - paragraph [ref=e121]:
              - img [ref=e122]
              - text: Colosseum main entrance
          - generic [ref=e125]:
            - heading "Cancellation Policy" [level=2] [ref=e126]
            - paragraph [ref=e127]: Free cancellation up to 24 hours before the tour.
          - region "Reviews" [ref=e128]:
            - heading "Reviews" [level=2] [ref=e129]
            - generic [ref=e130]:
              - heading "Reviews" [level=3] [ref=e131]
              - status [ref=e132]: Loading...
        - complementary [ref=e142]:
          - generic [ref=e144]:
            - generic [ref=e146]:
              - text: €45.00
              - generic [ref=e147]: / person
            - generic [ref=e148]:
              - generic [ref=e149]: Select a Date
              - generic [ref=e150]:
                - paragraph [ref=e151]: Next available
                - paragraph [ref=e152]: 10/17/2026
              - combobox "Select a Date" [ref=e153]:
                - option "Select a Date"
                - option "10/17/2026" [selected]
                - option "10/18/2026"
                - option "10/19/2026"
                - option "10/20/2026"
                - option "10/21/2026"
                - option "10/22/2026"
                - option "10/23/2026"
                - option "10/24/2026"
                - option "10/25/2026"
                - option "10/26/2026"
                - option "10/27/2026"
                - option "10/28/2026"
                - option "10/29/2026"
                - option "10/30/2026"
                - option "10/31/2026"
                - option "11/01/2026"
                - option "11/02/2026"
                - option "11/03/2026"
                - option "11/04/2026"
                - option "11/05/2026"
                - option "11/06/2026"
                - option "11/07/2026"
                - option "11/08/2026"
                - option "11/09/2026"
                - option "11/10/2026"
                - option "11/11/2026"
                - option "11/12/2026"
                - option "11/13/2026"
                - option "11/14/2026"
                - option "11/15/2026"
                - option "11/16/2026"
                - option "11/17/2026"
                - option "11/18/2026"
                - option "11/19/2026"
                - option "11/20/2026"
                - option "11/21/2026"
                - option "11/22/2026"
                - option "11/23/2026"
                - option "11/24/2026"
                - option "11/25/2026"
                - option "11/26/2026"
                - option "11/27/2026"
                - option "11/28/2026"
                - option "11/29/2026"
                - option "11/30/2026"
                - option "12/01/2026"
                - option "12/02/2026"
                - option "12/03/2026"
                - option "12/04/2026"
                - option "12/05/2026"
                - option "12/06/2026"
                - option "12/07/2026"
                - option "12/08/2026"
                - option "12/09/2026"
                - option "12/10/2026"
                - option "12/11/2026"
                - option "12/12/2026"
                - option "12/13/2026"
                - option "12/14/2026"
            - generic [ref=e154]:
              - generic [ref=e155]: Participants
              - group "Participants" [ref=e156]:
                - button "Decrease participants" [disabled] [ref=e157]: "-"
                - status [ref=e158]: "1"
                - button "Increase participants" [ref=e159]: +
                - generic [ref=e160]: 1–20 allowed
            - paragraph [ref=e161]: "Estimated total: €45.00"
            - paragraph [ref=e162]: Free cancellation up to 24 hours before the tour.
            - generic [ref=e163]:
              - paragraph [ref=e164]: Sign in to continue booking. Your tour, date, traveler count and language are kept, and nothing is booked until you confirm.
              - link "Book Now" [ref=e165] [cursor=pointer]:
                - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
              - link "Create account" [ref=e166] [cursor=pointer]:
                - /url: /en/auth/register?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
      - region "More experiences to explore" [ref=e167]:
        - heading "More experiences to explore" [level=2] [ref=e168]
        - generic [ref=e169]:
          - generic [ref=e170]:
            - 'link "Venice Hidden Canals Walking Tour Next: Oct 17, 2026 Venice, Italy 2.5 hours Venice Hidden Canals Walking Tour Walking Tours €55.00" [ref=e171] [cursor=pointer]':
              - /url: /en/tours/venice-walking-tour-hidden-canals
              - generic [ref=e172]:
                - img "Venice Hidden Canals Walking Tour" [ref=e173]
                - generic [ref=e174]: "Next: Oct 17, 2026"
              - generic [ref=e175]:
                - generic [ref=e176]:
                  - generic [ref=e177]: Venice, Italy
                  - generic [ref=e178]: ·
                  - generic [ref=e179]: 2.5 hours
                - heading "Venice Hidden Canals Walking Tour" [level=2] [ref=e180]
                - generic [ref=e181]:
                  - generic [ref=e182]: Walking Tours
                  - generic [ref=e183]: €55.00
            - button "Save to wishlist" [ref=e185]:
              - generic [ref=e186]: ♡
          - generic [ref=e187]:
            - 'link "Barcelona Gaudí Architecture Masterpieces Tour Next: Oct 17, 2026 Barcelona, Spain 4 hours Barcelona Gaudí Architecture Masterpieces Tour Walking Tours €65.00" [ref=e188] [cursor=pointer]':
              - /url: /en/tours/barcelona-gaudi-architecture-tour
              - generic [ref=e189]:
                - img "Barcelona Gaudí Architecture Masterpieces Tour" [ref=e190]
                - generic [ref=e191]: "Next: Oct 17, 2026"
              - generic [ref=e192]:
                - generic [ref=e193]:
                  - generic [ref=e194]: Barcelona, Spain
                  - generic [ref=e195]: ·
                  - generic [ref=e196]: 4 hours
                - heading "Barcelona Gaudí Architecture Masterpieces Tour" [level=2] [ref=e197]
                - generic [ref=e198]:
                  - generic [ref=e199]: Walking Tours
                  - generic [ref=e200]: €65.00
            - button "Save to wishlist" [ref=e202]:
              - generic [ref=e203]: ♡
          - generic [ref=e204]:
            - 'link "Montmartre Artists Quarter Walking Tour Next: Oct 17, 2026 Paris, France 2.5 hours Montmartre Artists Quarter Walking Tour Walking Tours €49.00" [ref=e205] [cursor=pointer]':
              - /url: /en/tours/paris-montmartre-artists-quarter
              - generic [ref=e206]:
                - img "Montmartre Artists Quarter Walking Tour" [ref=e207]
                - generic [ref=e208]: "Next: Oct 17, 2026"
              - generic [ref=e209]:
                - generic [ref=e210]:
                  - generic [ref=e211]: Paris, France
                  - generic [ref=e212]: ·
                  - generic [ref=e213]: 2.5 hours
                - heading "Montmartre Artists Quarter Walking Tour" [level=2] [ref=e214]
                - generic [ref=e215]:
                  - generic [ref=e216]: Walking Tours
                  - generic [ref=e217]: €49.00
            - button "Save to wishlist" [ref=e219]:
              - generic [ref=e220]: ♡
  - contentinfo [ref=e221]:
    - generic [ref=e222]:
      - generic [ref=e223]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e226]
        - generic [ref=e227]:
          - paragraph [ref=e228]: Explore
          - list [ref=e229]:
            - listitem [ref=e230]:
              - link "Search tours" [ref=e231] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e232]:
              - link "Categories" [ref=e233] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e234]:
              - link "Destinations" [ref=e235] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e236]:
          - paragraph [ref=e237]: Company
          - list [ref=e238]:
            - listitem [ref=e239]:
              - link "Privacy policy" [ref=e240] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e241]:
              - link "Terms of service" [ref=e242] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e243]:
        - generic [ref=e244]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e246]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e247]:
      - generic [ref=e248]: We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies.
      - generic [ref=e249]:
        - button "Reject" [ref=e250]
        - button "Accept" [ref=e251]
  - alert [ref=e252]
```

# Test source

```ts
  290 | 
  291 |     // Canonical gallery: one photo DOM (scroll-snap rail below 1024px,
  292 |     // mosaic at >=1024px); thumbnails no longer exist.
  293 |     const gallery = page.getByTestId('tour-gallery');
  294 |     await expect(gallery).toBeVisible({ timeout: 10000 });
  295 |     await expect(gallery.getByRole('list', { name: /thumbnail/i })).toHaveCount(0);
  296 |     const counter = gallery.getByText(/^\d+ of \d+$/);
  297 |     await expect(counter).toBeVisible();
  298 |     const initial = await counter.textContent();
  299 |     const total = Number(initial?.match(/of (\d+)$/)?.[1] ?? '0');
  300 |     expect(total).toBeGreaterThan(0);
  301 |     if (total > 1) {
  302 |       await gallery.getByRole('button', { name: 'Next image' }).click();
  303 |       await expect(counter).toHaveText(`2 of ${total}`);
  304 |       await gallery.getByRole('button', { name: 'Previous image' }).click();
  305 |       await expect(counter).toHaveText(`1 of ${total}`);
  306 |     } else {
  307 |       await expect(gallery.getByRole('button', { name: 'Next image' })).toHaveCount(0);
  308 |     }
  309 |   });
  310 | 
  311 |   test('lightbox opens from a keyboard-focusable tile and restores focus on Escape', async ({ page }) => {
  312 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  313 | 
  314 |     const trigger = page.getByRole('button', { name: 'View image 1' });
  315 |     await expect(trigger).toBeVisible();
  316 |     await trigger.focus();
  317 |     await page.keyboard.press('Enter');
  318 |     await expect(page.getByRole('dialog', { name: 'Image lightbox' })).toBeVisible();
  319 |     await expect(page.getByRole('button', { name: 'Close lightbox' })).toBeFocused();
  320 |     await page.keyboard.press('Escape');
  321 |     await expect(page.getByRole('dialog', { name: 'Image lightbox' })).not.toBeVisible();
  322 |     await expect(trigger).toBeFocused();
  323 |   });
  324 | 
  325 |   test('section navigation links only to rendered sections', async ({ page }) => {
  326 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  327 |     const nav = page.getByRole('navigation', { name: 'Tour sections' });
  328 |     await expect(nav).toBeVisible();
  329 |     const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
  330 |     expect(hrefs.length).toBeGreaterThan(0);
  331 |     for (const href of hrefs) {
  332 |       expect(href).toMatch(/^#[a-z-]+$/);
  333 |       await expect(page.locator(href!)).toHaveCount(1);
  334 |     }
  335 |   });
  336 | 
  337 |   test('availability calendar shows available dates', async ({ page }) => {
  338 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  339 | 
  340 |     const calendar = page.getByText('Select a Date');
  341 |     await expect(calendar).toBeVisible({ timeout: 10000 });
  342 |   });
  343 | 
  344 |   test('booking CTA is visible', async ({ page }) => {
  345 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  346 | 
  347 |     const bookNow = page.getByRole('button', { name: /Book Now/i });
  348 |     const bookNowLink = page.getByRole('link', { name: /Book Now/i });
  349 | 
  350 |     const hasCTA = (await bookNow.isVisible().catch(() => false))
  351 |       || (await bookNowLink.isVisible().catch(() => false));
  352 | 
  353 |     // May show "Currently Unavailable" instead
  354 |     const unavailable = page.getByText('Currently Unavailable');
  355 |     const hasUnavailable = await unavailable.isVisible().catch(() => false);
  356 | 
  357 |     expect(hasCTA || hasUnavailable).toBeTruthy();
  358 |   });
  359 | 
  360 |   test('participant selector increments and decrements', async ({ page }) => {
  361 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  362 | 
  363 |     const increaseBtn = page.getByRole('button', { name: 'Increase participants' });
  364 |     const decreaseBtn = page.getByRole('button', { name: 'Decrease participants' });
  365 | 
  366 |     if (await increaseBtn.isVisible()) {
  367 |       await increaseBtn.click();
  368 |       // Count should change - exact value depends on tour's group size
  369 |       await expect(page.getByText(/allowed/)).toBeVisible();
  370 |       await expect(decreaseBtn).toBeVisible();
  371 |     }
  372 |   });
  373 | 
  374 |   test('shows 404 page for non-existent tour', async ({ page }) => {
  375 |     await page.goto('/en/tours/this-tour-does-not-exist-xyz');
  376 | 
  377 |     // Next.js default not-found behavior or API error
  378 |     await expect(page.locator('main')).toBeVisible();
  379 |   });
  380 | 
  381 |   test('shows the reviews section only when the API reports genuine reviews', async ({ page, request }) => {
  382 |     const response = await request.get('/api/public/tours/hidden-gems-rome-walking-tour?locale=en');
  383 |     expect(response.ok()).toBeTruthy();
  384 |     const { data } = await response.json();
  385 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  386 | 
  387 |     const reviewsHeading = page.getByRole('heading', { name: /Reviews/i });
  388 |     const reviewsLink = page.getByRole('navigation', { name: 'Tour sections' }).getByRole('link', { name: 'Reviews' });
  389 |     if (data.reviews.count > 0) {
> 390 |       await expect(reviewsHeading).toBeVisible();
      |                                    ^ Error: expect(locator).toBeVisible() failed
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
  481 |     expect(href).toMatch(/^\/en\/booking\?tour=hidden-gems-rome-walking-tour&participants=1&date=\d{4}-\d{2}-\d{2}$/);
  482 |     await action.focus();
  483 |     await expect(action).toBeFocused();
  484 |   });
  485 | 
  486 |   for (const [locale, bookNow, selectDate] of [
  487 |     ['en', 'Book Now', 'Select a Date'],
  488 |     ['es', 'Reservar Ahora', 'Seleccionar Fecha'],
  489 |     ['it', 'Prenota Ora', 'Seleziona Data'],
  490 |   ]) {
```