# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> availability calendar shows available dates
- Location: tests/e2e/tour-detail.spec.ts:340:7

# Error details

```
Test timeout of 60000ms exceeded.
```

```
Error: locator.click: Test timeout of 60000ms exceeded.
Call log:
  - waiting for getByTestId('mobile-booking-bar').getByRole('button')
    - locator resolved to <button type="button" class="inline-flex min-h-11 items-center justify-center py-1 text-center text-xs font-medium text-bookly-navy underline underline-offset-2">Edit booking selections</button>
  - attempting click action
    2 × waiting for element to be visible, enabled and stable
      - element is visible, enabled and stable
      - scrolling into view if needed
      - done scrolling
      - <div class="flex w-full items-center justify-end gap-3 sm:w-auto">…</div> from <aside aria-label="Cookie consent">…</aside> subtree intercepts pointer events
    - retrying click action
    - waiting 20ms
    2 × waiting for element to be visible, enabled and stable
      - element is visible, enabled and stable
      - scrolling into view if needed
      - done scrolling
      - <div class="flex w-full items-center justify-end gap-3 sm:w-auto">…</div> from <aside aria-label="Cookie consent">…</aside> subtree intercepts pointer events
    - retrying click action
      - waiting 100ms
    115 × waiting for element to be visible, enabled and stable
        - element is visible, enabled and stable
        - scrolling into view if needed
        - done scrolling
        - <div class="flex w-full items-center justify-end gap-3 sm:w-auto">…</div> from <aside aria-label="Cookie consent">…</aside> subtree intercepts pointer events
      - retrying click action
        - waiting 500ms

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
              - generic [ref=e134]:
                - generic [ref=e135]: "4.5"
                - generic [ref=e136]:
                  - 'img "Rating: 4.5 out of 5" [ref=e137]':
                    - img [ref=e138]
                    - img [ref=e140]
                    - img [ref=e142]
                    - img [ref=e144]
                    - img [ref=e146]
                  - paragraph [ref=e148]: 2 reviews
              - generic [ref=e149]:
                - generic [ref=e150]:
                  - generic [ref=e151]:
                    - generic [ref=e152]: Test
                    - generic [ref=e153]: Edited
                  - generic [ref=e154]:
                    - 'img "Rating: 4 out of 5" [ref=e155]':
                      - img [ref=e156]
                      - img [ref=e158]
                      - img [ref=e160]
                      - img [ref=e162]
                      - img [ref=e164]
                    - generic [ref=e166]: September 27, 2026
                  - paragraph [ref=e167]: Updated comment value here.
                - generic [ref=e168]:
                  - generic [ref=e170]: Test
                  - generic [ref=e171]:
                    - 'img "Rating: 5 out of 5" [ref=e172]':
                      - img [ref=e173]
                      - img [ref=e175]
                      - img [ref=e177]
                      - img [ref=e179]
                      - img [ref=e181]
                    - generic [ref=e183]: September 12, 2026
                  - paragraph [ref=e184]: A wonderful hidden-gems walk through Rome — highly recommended!
        - complementary:
          - generic [ref=e186]:
            - paragraph [ref=e187]:
              - generic [ref=e188]: €45.00 / person
              - generic [ref=e189]: 10/17/2026 · 1 · €45.00
            - generic [ref=e190]:
              - link "Continue to booking" [ref=e191] [cursor=pointer]:
                - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
              - button "Edit booking selections" [ref=e192]
      - region "More experiences to explore" [ref=e193]:
        - heading "More experiences to explore" [level=2] [ref=e194]
        - generic [ref=e195]:
          - generic [ref=e196]:
            - 'link "Venice Hidden Canals Walking Tour Next: Oct 17, 2026 Venice, Italy 2.5 hours Venice Hidden Canals Walking Tour Walking Tours €55.00" [ref=e197] [cursor=pointer]':
              - /url: /en/tours/venice-walking-tour-hidden-canals
              - generic [ref=e198]:
                - img "Venice Hidden Canals Walking Tour" [ref=e199]
                - generic [ref=e200]: "Next: Oct 17, 2026"
              - generic [ref=e201]:
                - generic [ref=e202]:
                  - generic [ref=e203]: Venice, Italy
                  - generic [ref=e204]: ·
                  - generic [ref=e205]: 2.5 hours
                - heading "Venice Hidden Canals Walking Tour" [level=2] [ref=e206]
                - generic [ref=e207]:
                  - generic [ref=e208]: Walking Tours
                  - generic [ref=e209]: €55.00
            - button "Save to wishlist" [ref=e211]:
              - generic [ref=e212]: ♡
          - generic [ref=e213]:
            - 'link "Barcelona Gaudí Architecture Masterpieces Tour Next: Oct 17, 2026 Barcelona, Spain 4 hours Barcelona Gaudí Architecture Masterpieces Tour Walking Tours €65.00" [ref=e214] [cursor=pointer]':
              - /url: /en/tours/barcelona-gaudi-architecture-tour
              - generic [ref=e215]:
                - img "Barcelona Gaudí Architecture Masterpieces Tour" [ref=e216]
                - generic [ref=e217]: "Next: Oct 17, 2026"
              - generic [ref=e218]:
                - generic [ref=e219]:
                  - generic [ref=e220]: Barcelona, Spain
                  - generic [ref=e221]: ·
                  - generic [ref=e222]: 4 hours
                - heading "Barcelona Gaudí Architecture Masterpieces Tour" [level=2] [ref=e223]
                - generic [ref=e224]:
                  - generic [ref=e225]: Walking Tours
                  - generic [ref=e226]: €65.00
            - button "Save to wishlist" [ref=e228]:
              - generic [ref=e229]: ♡
          - generic [ref=e230]:
            - 'link "Montmartre Artists Quarter Walking Tour Next: Oct 17, 2026 Paris, France 2.5 hours Montmartre Artists Quarter Walking Tour Walking Tours €49.00" [ref=e231] [cursor=pointer]':
              - /url: /en/tours/paris-montmartre-artists-quarter
              - generic [ref=e232]:
                - img "Montmartre Artists Quarter Walking Tour" [ref=e233]
                - generic [ref=e234]: "Next: Oct 17, 2026"
              - generic [ref=e235]:
                - generic [ref=e236]:
                  - generic [ref=e237]: Paris, France
                  - generic [ref=e238]: ·
                  - generic [ref=e239]: 2.5 hours
                - heading "Montmartre Artists Quarter Walking Tour" [level=2] [ref=e240]
                - generic [ref=e241]:
                  - generic [ref=e242]: Walking Tours
                  - generic [ref=e243]: €49.00
            - button "Save to wishlist" [ref=e245]:
              - generic [ref=e246]: ♡
  - contentinfo [ref=e247]:
    - generic [ref=e248]:
      - generic [ref=e249]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e252]
        - generic [ref=e253]:
          - paragraph [ref=e254]: Explore
          - list [ref=e255]:
            - listitem [ref=e256]:
              - link "Search tours" [ref=e257] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e258]:
              - link "Categories" [ref=e259] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e260]:
              - link "Destinations" [ref=e261] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e262]:
          - paragraph [ref=e263]: Company
          - list [ref=e264]:
            - listitem [ref=e265]:
              - link "Privacy policy" [ref=e266] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e267]:
              - link "Terms of service" [ref=e268] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e269]:
        - generic [ref=e270]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e272]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e273]:
      - generic [ref=e274]: We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies.
      - generic [ref=e275]:
        - button "Reject" [ref=e276]
        - button "Accept" [ref=e277]
  - alert [ref=e278]
```

# Test source

```ts
  244 |           seo: { ...source.seo },
  245 |         };
  246 |         const servedReady = await readServedDetail(mockPort, slug, scenario.locale);
  247 |         expect(servedReady.content_locale).toBe(scenario.locale);
  248 |         expect(servedReady.itinerary_locale).toBe(scenario.locale);
  249 |         expect(servedReady.translation_warning).toBeUndefined();
  250 | 
  251 |         await page.reload();
  252 |         expect(page.url()).toBe(url);
  253 |         const readyHeading = page.getByRole('heading', { level: 1 });
  254 |         await expect(readyHeading).toHaveText(source.title);
  255 |         await expect(readyHeading).toHaveAttribute('lang', scenario.locale);
  256 |         await expect(page.locator('main#main-content #itinerary')).toContainText(scenario.day);
  257 |         await expect(page.locator('main#main-content #itinerary')).not.toContainText('English arrival');
  258 |         await expect(page.locator('main#main-content #itinerary h3 span').first()).toHaveAttribute('lang', scenario.locale);
  259 |         await expect(page.locator('main#main-content').getByText(scenario.itineraryNotice)).toHaveCount(0);
  260 |         await expect(page.locator('main#main-content').getByText(scenario.contentNotice)).toHaveCount(0);
  261 |         await expect(page.locator('main#main-content').getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
  262 |         const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
  263 |         expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
  264 |         expect(schema['@context'].name['@language']).toBe(scenario.locale);
  265 |         expect(schema.mainEntityOfPage.inLanguage).toBe(scenario.locale);
  266 |         expect(schema.inLanguage).toBeUndefined();
  267 |       }
  268 |     } finally {
  269 |       await harness.stop();
  270 |     }
  271 |   });
  272 |   test('tour detail page loads with all sections', async ({ page }) => {
  273 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  274 | 
  275 |     // Page should load
  276 |     await expect(page.locator('main')).toBeVisible();
  277 | 
  278 |     // Title should be present
  279 |     await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  280 |   });
  281 | 
  282 |   test('image gallery is visible', async ({ page }) => {
  283 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  284 | 
  285 |     const gallery = page.locator('.aspect-\\[16\\/10\\]').first();
  286 |     await expect(gallery).toBeVisible({ timeout: 10000 });
  287 |   });
  288 | 
  289 |   test('image gallery rail controls move the counter with no thumbnail navigation', async ({ page }) => {
  290 |     // Mobile rail: the counter and rail arrows are the <1024px controls.
  291 |     await page.setViewportSize({ width: 390, height: 844 });
  292 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  293 | 
  294 |     // Canonical gallery: one photo DOM (scroll-snap rail below 1024px,
  295 |     // mosaic at >=1024px); thumbnails no longer exist.
  296 |     const gallery = page.getByTestId('tour-gallery');
  297 |     await expect(gallery).toBeVisible({ timeout: 10000 });
  298 |     await expect(gallery.getByRole('list', { name: /thumbnail/i })).toHaveCount(0);
  299 |     const counter = gallery.getByText(/^\d+ of \d+$/);
  300 |     await expect(counter).toBeVisible();
  301 |     const initial = await counter.textContent();
  302 |     const total = Number(initial?.match(/of (\d+)$/)?.[1] ?? '0');
  303 |     expect(total).toBeGreaterThan(0);
  304 |     if (total > 1) {
  305 |       await gallery.getByRole('button', { name: 'Next image' }).click();
  306 |       await expect(counter).toHaveText(`2 of ${total}`);
  307 |       await gallery.getByRole('button', { name: 'Previous image' }).click();
  308 |       await expect(counter).toHaveText(`1 of ${total}`);
  309 |     } else {
  310 |       await expect(gallery.getByRole('button', { name: 'Next image' })).toHaveCount(0);
  311 |     }
  312 |   });
  313 | 
  314 |   test('lightbox opens from a keyboard-focusable tile and restores focus on Escape', async ({ page }) => {
  315 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  316 | 
  317 |     const trigger = page.getByRole('button', { name: 'View image 1' });
  318 |     await expect(trigger).toBeVisible();
  319 |     await trigger.focus();
  320 |     await page.keyboard.press('Enter');
  321 |     await expect(page.getByRole('dialog', { name: 'Image lightbox' })).toBeVisible();
  322 |     await expect(page.getByRole('button', { name: 'Close lightbox' })).toBeFocused();
  323 |     await page.keyboard.press('Escape');
  324 |     await expect(page.getByRole('dialog', { name: 'Image lightbox' })).not.toBeVisible();
  325 |     await expect(trigger).toBeFocused();
  326 |   });
  327 | 
  328 |   test('section navigation links only to rendered sections', async ({ page }) => {
  329 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  330 |     const nav = page.getByRole('navigation', { name: 'Tour sections' });
  331 |     await expect(nav).toBeVisible();
  332 |     const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
  333 |     expect(hrefs.length).toBeGreaterThan(0);
  334 |     for (const href of hrefs) {
  335 |       expect(href).toMatch(/^#[a-z-]+$/);
  336 |       await expect(page.locator(href!)).toHaveCount(1);
  337 |     }
  338 |   });
  339 | 
  340 |   test('availability calendar shows available dates', async ({ page }) => {
  341 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  342 | 
  343 |     if ((page.viewportSize()?.width ?? 0) < 1024) {
> 344 |       await page.getByTestId('mobile-booking-bar').getByRole('button').click();
      |                                                                        ^ Error: locator.click: Test timeout of 60000ms exceeded.
  345 |     }
  346 |     const calendar = page.getByRole('combobox', { name: 'Select a Date', exact: true });
  347 |     await expect(calendar).toBeVisible({ timeout: 10000 });
  348 |   });
  349 | 
  350 |   test('booking CTA is visible', async ({ page }) => {
  351 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  352 | 
  353 |     const bookNow = page.getByRole('button', { name: /Book Now/i });
  354 |     const bookNowLink = page.getByRole('link', { name: /Book Now/i });
  355 | 
  356 |     const hasCTA = (await bookNow.isVisible().catch(() => false))
  357 |       || (await bookNowLink.isVisible().catch(() => false));
  358 | 
  359 |     // May show "Currently Unavailable" instead
  360 |     const unavailable = page.getByText('Currently Unavailable');
  361 |     const hasUnavailable = await unavailable.isVisible().catch(() => false);
  362 | 
  363 |     expect(hasCTA || hasUnavailable).toBeTruthy();
  364 |   });
  365 | 
  366 |   test('participant selector increments and decrements', async ({ page }) => {
  367 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  368 | 
  369 |     const increaseBtn = page.getByRole('button', { name: 'Increase participants' });
  370 |     const decreaseBtn = page.getByRole('button', { name: 'Decrease participants' });
  371 | 
  372 |     if (await increaseBtn.isVisible()) {
  373 |       await increaseBtn.click();
  374 |       // Count should change - exact value depends on tour's group size
  375 |       await expect(page.getByText(/allowed/)).toBeVisible();
  376 |       await expect(decreaseBtn).toBeVisible();
  377 |     }
  378 |   });
  379 | 
  380 |   test('shows 404 page for non-existent tour', async ({ page }) => {
  381 |     await page.goto('/en/tours/this-tour-does-not-exist-xyz');
  382 | 
  383 |     // Next.js default not-found behavior or API error
  384 |     await expect(page.locator('main')).toBeVisible();
  385 |   });
  386 | 
  387 |   test('shows the reviews section only when the API reports genuine reviews', async ({ page, request }) => {
  388 |     const response = await request.get('/api/public/tours/hidden-gems-rome-walking-tour?locale=en');
  389 |     expect(response.ok()).toBeTruthy();
  390 |     const { data } = await response.json();
  391 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  392 | 
  393 |     const reviewsHeading = page.getByRole('heading', { level: 2, name: 'Reviews', exact: true });
  394 |     const reviewsLink = page.getByRole('navigation', { name: 'Tour sections' }).getByRole('link', { name: 'Reviews' });
  395 |     if (data.reviews.count > 0) {
  396 |       await expect(reviewsHeading).toBeVisible();
  397 |       await expect(reviewsLink).toBeVisible();
  398 |     } else {
  399 |       await expect(reviewsHeading).toHaveCount(0);
  400 |       await expect(reviewsLink).toHaveCount(0);
  401 |     }
  402 |   });
  403 | 
  404 |   test('about section displays description', async ({ page }) => {
  405 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  406 | 
  407 |     const aboutHeading = page.getByRole('heading', { name: 'About This Tour' });
  408 |     await expect(aboutHeading).toBeVisible({ timeout: 10000 });
  409 |   });
  410 | 
  411 |   test('tour detail has proper SEO metadata', async ({ page }) => {
  412 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  413 | 
  414 |     await expect(page).toHaveTitle(/Bookly/);
  415 | 
  416 |     // Check canonical link
  417 |     const canonical = page.locator('link[rel="canonical"]');
  418 |     if (await canonical.isVisible().catch(() => false)) {
  419 |       const href = await canonical.getAttribute('href');
  420 |       expect(href).toContain('/tours/');
  421 |     }
  422 |   });
  423 | 
  424 |   // ─── Responsive Viewport Tests (FR-032, T092) ───
  425 | 
  426 |   const viewports = [
  427 |     { name: 'mobile', width: 375, height: 667 },
  428 |     { name: 'tablet', width: 768, height: 1024 },
  429 |     { name: 'desktop', width: 1280, height: 800 },
  430 |   ];
  431 | 
  432 |   for (const vp of viewports) {
  433 |     test(`tour detail page adapts to ${vp.name} viewport (${vp.width}px) without horizontal scroll`, async ({ page }) => {
  434 |       await page.setViewportSize({ width: vp.width, height: vp.height });
  435 |       await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  436 | 
  437 |       await expect(page.locator('main')).toBeVisible({ timeout: 10000 });
  438 | 
  439 |       const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
  440 |       const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
  441 |       expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  442 |     });
  443 |   }
  444 | 
```