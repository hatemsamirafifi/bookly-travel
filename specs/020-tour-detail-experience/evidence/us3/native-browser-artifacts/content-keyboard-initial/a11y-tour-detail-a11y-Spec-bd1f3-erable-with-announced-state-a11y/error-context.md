# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: a11y\tour-detail-a11y.spec.ts >> Spec020 US6 locale, width and interaction acceptance >> long-overview disclosure is fully keyboard operable with announced state
- Location: tests\e2e\a11y\tour-detail-a11y.spec.ts:232:7

# Error details

```
Error: expect(locator).toHaveAttribute(expected) failed

Locator: locator('#overview').getByRole('button', { name: 'Read more' })
Expected: "true"
Timeout: 5000ms
Error: element(s) not found

Call log:
  - Expect "toHaveAttribute" with timeout 5000ms
  - waiting for locator('#overview').getByRole('button', { name: 'Read more' })

```

```yaml
- banner:
  - link "Skip to main content":
    - /url: "#main-content"
  - link "Bookly":
    - /url: /en
  - navigation "Main navigation":
    - link "Home":
      - /url: /en
    - link "Search tours":
      - /url: /en/search
    - link "Categories":
      - /url: /en/categories
    - link "Destinations":
      - /url: /en/destinations
  - button "Search tours"
  - combobox "Switch language":
    - option "English" [selected]
    - option "Español"
    - option "Italiano"
  - link "Sign In":
    - /url: /en/auth/login
  - link "Sign Up":
    - /url: /en/auth/register
- main:
  - navigation "Breadcrumbs":
    - list:
      - listitem:
        - link "Home":
          - /url: /en
      - listitem:
        - link "Walking Tours":
          - /url: /en/categories/walking
      - listitem: Hidden Gems of Rome Walking Tour
  - heading "Hidden Gems of Rome Walking Tour" [level=1]
  - text: "Rome, Italy Rating: 4.5 out of 5"
  - link "(2 Reviews)":
    - /url: "#reviews"
  - button "View image 1": This photo is unavailable.
  - button "View image 2": This photo is unavailable.
  - button "View image 3": This photo is unavailable.
  - button "View image 4": This photo is unavailable.
  - button "View image 5": This photo is unavailable.
  - button "See all 5 photos"
  - navigation "Tour sections":
    - link "About This Tour":
      - /url: "#overview"
    - link "Tour facts":
      - /url: "#facts"
    - link "Highlights":
      - /url: "#highlights"
    - link "Itinerary":
      - /url: "#itinerary"
    - link "What's Included":
      - /url: "#included"
    - link "What's Excluded":
      - /url: "#excluded"
    - link "Meeting Point":
      - /url: "#meeting"
    - link "Cancellation Policy":
      - /url: "#cancellation"
    - link "Important information":
      - /url: "#important"
    - link "Tour operator":
      - /url: "#operator"
    - link "Reviews":
      - /url: "#reviews"
    - link "More experiences to explore":
      - /url: "#related"
  - article:
    - heading "About This Tour" [level=2]
    - text: Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Tuscan sun. Final reachable sentence.
    - button "Read less" [expanded]
    - heading "Tour facts" [level=2]
    - term: Duration
    - definition: 3 hours
    - term: Group size
    - definition: 1–20 travelers
    - term: Difficulty
    - definition: Moderate
    - paragraph: "Live guide: German, English, Spanish"
    - heading "Highlights" [level=2]
    - list:
      - listitem: Hidden courtyards
      - listitem: Local artisan shops
      - listitem: Ancient Roman stories
      - listitem: Off the beaten path
    - heading "Itinerary" [level=2]
    - list:
      - listitem:
        - 'heading "Day 1: Hidden courtyards walk" [level=3]'
        - paragraph: Walk the secret corners of Rome.
      - listitem:
        - 'heading "Day 2: Artisan quarter" [level=3]'
        - paragraph: Meet local artisans.
    - heading "What's Included" [level=3]:
      - button "What's Included"
    - heading "What's Excluded" [level=3]:
      - button "What's Excluded"
    - heading "Meeting Point" [level=2]
    - paragraph: Colosseum main entrance
    - heading "Cancellation Policy" [level=2]
    - paragraph: Free cancellation up to 24 hours before the tour.
    - heading "Important information" [level=2]
    - list:
      - listitem: Bring your passport
    - region "Tour operator":
      - heading "Tour operator" [level=2]
      - paragraph: Rome Walks
      - paragraph: Verified operator
      - paragraph: Local guides.
      - text: 3 tours 8 reviews
    - heading "Reviews" [level=3]
    - text: "4.5"
    - radiogroup "Star rating":
      - radio "1 star" [disabled]
      - radio "2 stars" [disabled]
      - radio "3 stars" [disabled]
      - radio "4 stars" [disabled]
      - radio "5 stars" [checked] [disabled]
    - paragraph: 2 reviews
    - text: Test Edited
    - radiogroup "Star rating":
      - radio "1 star" [disabled]
      - radio "2 stars" [disabled]
      - radio "3 stars" [disabled]
      - radio "4 stars" [checked] [disabled]
      - radio "5 stars" [disabled]
    - text: September 28, 2026
    - paragraph: Updated comment value here.
    - text: Test
    - radiogroup "Star rating":
      - radio "1 star" [disabled]
      - radio "2 stars" [disabled]
      - radio "3 stars" [disabled]
      - radio "4 stars" [disabled]
      - radio "5 stars" [checked] [disabled]
    - text: September 12, 2026
    - paragraph: A wonderful hidden-gems walk through Rome — highly recommended!
  - complementary:
    - text: €45.00 / person
    - button "Save to wishlist"
    - text: Select a Date
    - paragraph: Next available
    - paragraph: 10/17/2026
    - combobox "Select a Date":
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
      - option "12/15/2026"
    - text: Participants
    - group "Participants":
      - button "Decrease participants" [disabled]: "-"
      - status: "1"
      - button "Increase participants": +
      - text: 1–20 allowed
    - paragraph: "Estimated total: €45.00"
    - paragraph: Free cancellation up to 24 hours before the tour.
    - paragraph: Sign in to continue booking. Your tour, date, traveler count and language are kept, and nothing is booked until you confirm.
    - link "Book Now":
      - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
    - link "Create account":
      - /url: /en/auth/register?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
  - region "More experiences to explore":
    - heading "More experiences to explore" [level=2]
    - 'link "Roman Forum Small Group Rome, Italy 2 hours Roman Forum Small Group Rating: 4.8 out of 5 (4.8) (11 reviews) Walking Tours €39.00"':
      - /url: /en/tours/roman-forum-small-group
      - img "Roman Forum Small Group"
      - text: Rome, Italy 2 hours
      - heading "Roman Forum Small Group" [level=2]
      - 'img "Rating: 4.8 out of 5"'
      - text: (4.8) (11 reviews) Walking Tours €39.00
    - button "Save to wishlist"
- contentinfo:
  - img "Bookly Travel — Book smarter. Travel better."
  - paragraph: Explore
  - list:
    - listitem:
      - link "Search tours":
        - /url: /en/search
    - listitem:
      - link "Categories":
        - /url: /en/categories
    - listitem:
      - link "Destinations":
        - /url: /en/destinations
  - paragraph: Company
  - list:
    - listitem:
      - link "Privacy policy":
        - /url: /en/privacy
    - listitem:
      - link "Terms of service":
        - /url: /en/terms
  - text: © 2026 Bookly. All rights reserved.
  - combobox "Switch language":
    - option "English" [selected]
    - option "Español"
    - option "Italiano"
- complementary "Cookie consent":
  - text: We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies.
  - button "Reject"
  - button "Accept"
- alert
```

# Test source

```ts
  153 |     const galleryImg = page.locator('main#main-content img').first();
  154 |     await expect(galleryImg).toHaveAttribute('alt', /.+/);
  155 |     await expect(galleryImg).toHaveAttribute('lang', actualLang);
  156 | 
  157 |     // Localized control names still resolve in ES/IT (prefix match avoids
  158 |     // diacritic brittleness while proving page-language controls).
  159 |     await page.goto('/es/tours/' + slug);
  160 |     await expect(page.getByRole('button', { name: /Ver imagen/ }).first()).toBeVisible();
  161 |     await page.goto('/it/tours/' + slug);
  162 |     await expect(page.getByRole('button', { name: /Vedi immagine/ }).first()).toBeVisible();
  163 |   });
  164 | 
  165 |   test('lightbox dialog traps focus, closes on escape and restores trigger focus', async ({ page }) => {
  166 |     await page.goto('/en/tours/' + slug);
  167 |     await expect(page.locator('main#main-content')).toBeVisible();
  168 |     const trigger = page.getByRole('button', { name: 'View image 1' });
  169 |     await expect(trigger).toBeVisible();
  170 |     await trigger.focus();
  171 |     await page.keyboard.press('Enter');
  172 |     const dialog = page.getByRole('dialog', { name: 'Image lightbox' });
  173 |     await expect(dialog).toBeVisible();
  174 |     // Dialog carries no image lang so localized controls never inherit it.
  175 |     expect(await dialog.getAttribute('lang')).toBeNull();
  176 |     const closeButton = page.getByRole('button', { name: 'Close lightbox' });
  177 |     await expect(closeButton).toBeFocused();
  178 | 
  179 |     // Focus trap: cycling forward from the last button wraps to the first.
  180 |     await page.keyboard.press('Tab');
  181 |     const focusedAfterTab = await page.evaluate(() => document.activeElement?.getAttribute('aria-label'));
  182 |     expect(typeof focusedAfterTab === 'string' && focusedAfterTab.length > 0).toBeTruthy();
  183 |     // Backward wrap stays inside the dialog.
  184 |     await closeButton.focus();
  185 |     await page.keyboard.press('Shift+Tab');
  186 |     expect(await page.evaluate(() => document.activeElement?.closest('[role="dialog"]') !== null)).toBe(true);
  187 | 
  188 |     // Escape closes and restores focus to the invoking control.
  189 |     await page.keyboard.press('Escape');
  190 |     await expect(dialog).toHaveCount(0);
  191 |     await expect(trigger).toBeFocused();
  192 |     expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  193 |   });
  194 | });
  195 | 
  196 | // Spec 020 US6: twelve-combination locale/width acceptance plus
  197 | // keyboard/reduced-motion coverage for owned content/state surfaces.
  198 | // Gallery-viewer, section-nav-active and wishlist-prompt focus acceptance
  199 | // complete at T069 after US3/US4/US5 integrate those overlays; this slice
  200 | // claims no overlay acceptance and adds no gallery/nav/wishlist assertions.
  201 | test.describe('Spec020 US6 locale, width and interaction acceptance', () => {
  202 |   const slug = 'hidden-gems-rome-walking-tour';
  203 | 
  204 |   test('EN/ES/IT x 390/768/1024/1440 with loaded reviews has no axe violations', async ({ page, request }) => {
  205 |     test.setTimeout(300000);
  206 |     const reviewerByLocale: Record<string, string> = {};
  207 |     for (const locale of ['en', 'es', 'it']) {
  208 |       const response = await request.get(`/api/public/tours/${slug}/reviews?page=1&per_page=5`);
  209 |       expect(response.ok()).toBeTruthy();
  210 |       const { data } = await response.json();
  211 |       expect(data.length).toBeGreaterThan(0);
  212 |       reviewerByLocale[locale] = data[0].reviewer_name;
  213 |     }
  214 |     for (const locale of ['en', 'es', 'it']) {
  215 |       for (const width of [390, 768, 1024, 1440]) {
  216 |         await test.step(`${locale} at ${width}px`, async () => {
  217 |           await page.setViewportSize({ width, height: 900 });
  218 |           await page.goto(`/${locale}/tours/${slug}`);
  219 |           await expect(page.locator('main#main-content')).toBeVisible();
  220 |           // Wait for actual UI readiness: real API-backed review text, not placeholders.
  221 |           await expect(page.locator('#reviews').getByText(reviewerByLocale[locale], { exact: true }).first()).toBeVisible();
  222 |           // No unintended whole-page horizontal overflow at any acceptance width.
  223 |           expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
  224 |             await page.evaluate(() => document.documentElement.clientWidth),
  225 |           );
  226 |           expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  227 |         });
  228 |       }
  229 |     }
  230 |   });
  231 | 
  232 |   test('long-overview disclosure is fully keyboard operable with announced state', async ({ page }) => {
  233 |     test.setTimeout(180000);
  234 |     // Controlled SSR fixture with an asserted over-budget description, so the
  235 |     // disclosure is always present: no conditional no-op is possible.
  236 |     const longDescription = `${'Tuscan sun. '.repeat(80)}Final reachable sentence.`;
  237 |     expect(Array.from(longDescription).length).toBeGreaterThan(600);
  238 |     const detail = createFullTourDetail(new Date(), { description: longDescription });
  239 |     const harness = await startSsrHarness(slug, () => detail);
  240 |     try {
  241 |       await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  242 |       await expect(page.locator('main#main-content')).toBeVisible();
  243 |       const overview = page.locator('#overview');
  244 |       await expect(overview).toBeVisible();
  245 |       // Preview first: the full text is reachable only through the disclosure.
  246 |       await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
  247 |       const expand = overview.getByRole('button', { name: 'Read more' });
  248 |       await expect(expand).toHaveAttribute('aria-expanded', 'false');
  249 |       expect(await expand.getAttribute('aria-controls')).toBeTruthy();
  250 |       await expand.focus();
  251 |       await expect(expand).toBeFocused();
  252 |       await page.keyboard.press('Enter');
> 253 |       await expect(expand).toHaveAttribute('aria-expanded', 'true');
      |                            ^ Error: expect(locator).toHaveAttribute(expected) failed
  254 |       await expect(page.getByText('Final reachable sentence.')).toBeVisible();
  255 |       await expect(expand).toBeFocused();
  256 |       await page.keyboard.press('Enter');
  257 |       await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
  258 |       await expect(overview.getByRole('button', { name: 'Read more' })).toHaveAttribute('aria-expanded', 'false');
  259 |       expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  260 |     } finally {
  261 |       await harness.stop();
  262 |     }
  263 |   });
  264 | 
  265 |   test('page stays readable and operable with reduced motion preferred', async ({ page, request }) => {
  266 |     const response = await request.get(`/api/public/tours/${slug}?locale=es`);
  267 |     expect(response.ok()).toBeTruthy();
  268 |     await page.emulateMedia({ reducedMotion: 'reduce' });
  269 |     await page.goto('/es/tours/' + slug);
  270 |     await expect(page.locator('main#main-content')).toBeVisible();
  271 |     await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  272 |     const nav = page.getByRole('navigation', { name: /secciones|Tour sections/ });
  273 |     await expect(nav).toBeVisible();
  274 |     expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  275 |   });
  276 | });
  277 | 
```