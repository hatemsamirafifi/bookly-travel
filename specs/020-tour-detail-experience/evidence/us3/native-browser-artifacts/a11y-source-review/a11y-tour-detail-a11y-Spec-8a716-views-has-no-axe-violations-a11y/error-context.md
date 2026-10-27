# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: a11y\tour-detail-a11y.spec.ts >> Spec020 US6 locale, width and interaction acceptance >> EN/ES/IT x 390/768/1024/1440 with loaded reviews has no axe violations
- Location: tests\e2e\a11y\tour-detail-a11y.spec.ts:204:7

# Error details

```
Error: expect(received).toEqual(expected) // deep equality

- Expected  -  1
+ Received  + 64

- Array []
+ Array [
+   Object {
+     "description": "Ensure the contrast between foreground and background colors meets WCAG 2 AA minimum contrast ratio thresholds",
+     "help": "Elements must meet minimum color contrast ratio thresholds",
+     "helpUrl": "https://dequeuniversity.com/rules/axe/4.11/color-contrast?application=playwright",
+     "id": "color-contrast",
+     "impact": "serious",
+     "nodes": Array [
+       Object {
+         "all": Array [],
+         "any": Array [
+           Object {
+             "data": Object {
+               "bgColor": "#697a8b",
+               "contrastRatio": 4.41,
+               "expectedContrastRatio": "4.5:1",
+               "fgColor": "#ffffff",
+               "fontSize": "9.0pt (12px)",
+               "fontWeight": "normal",
+               "messageKey": null,
+             },
+             "id": "color-contrast",
+             "impact": "serious",
+             "message": "Element has insufficient color contrast of 4.41 (foreground color: #ffffff, background color: #697a8b, font size: 9.0pt (12px), font weight: normal). Expected contrast ratio of 4.5:1",
+             "relatedNodes": Array [
+               Object {
+                 "html": "<p aria-live=\"polite\" class=\"absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-overlay px-3 py-1 text-xs font-medium text-white lg:hidden\">1 of 5</p>",
+                 "target": Array [
+                   ".bottom-2",
+                 ],
+               },
+               Object {
+                 "html": "<body class=\"min-h-full flex flex-col font-sans\">",
+                 "target": Array [
+                   "body",
+                 ],
+               },
+             ],
+           },
+         ],
+         "failureSummary": "Fix any of the following:
+   Element has insufficient color contrast of 4.41 (foreground color: #ffffff, background color: #697a8b, font size: 9.0pt (12px), font weight: normal). Expected contrast ratio of 4.5:1",
+         "html": "<p aria-live=\"polite\" class=\"absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-overlay px-3 py-1 text-xs font-medium text-white lg:hidden\">1 of 5</p>",
+         "impact": "serious",
+         "none": Array [],
+         "target": Array [
+           ".bottom-2",
+         ],
+       },
+     ],
+     "tags": Array [
+       "cat.color",
+       "wcag2aa",
+       "wcag143",
+       "TTv5",
+       "TT13.c",
+       "EN-301-549",
+       "EN-9.1.4.3",
+       "ACT",
+       "RGAAv4",
+       "RGAA-3.2.1",
+     ],
+   },
+ ]
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
            - generic [ref=e49]:
              - img [ref=e50]
              - generic [ref=e52]: This photo is unavailable.
          - button "View image 2" [ref=e53]:
            - generic [ref=e54]:
              - img [ref=e55]
              - generic [ref=e57]: This photo is unavailable.
          - button "View image 3" [ref=e58]:
            - generic [ref=e59]:
              - img [ref=e60]
              - generic [ref=e62]: This photo is unavailable.
          - button "View image 4" [ref=e63]:
            - generic [ref=e64]:
              - img [ref=e65]
              - generic [ref=e67]: This photo is unavailable.
          - button "View image 5" [ref=e68]:
            - generic [ref=e69]:
              - img [ref=e70]
              - generic [ref=e72]: This photo is unavailable.
        - button "Previous image" [ref=e73]:
          - img [ref=e74]
        - button "Next image" [ref=e76]:
          - img [ref=e77]
        - paragraph [ref=e79]: 1 of 5
      - navigation "Tour sections" [ref=e80]:
        - generic [ref=e81]:
          - link "About This Tour" [ref=e82] [cursor=pointer]:
            - /url: "#overview"
          - link "Tour facts" [ref=e83] [cursor=pointer]:
            - /url: "#facts"
          - link "Highlights" [ref=e84] [cursor=pointer]:
            - /url: "#highlights"
          - link "What's Included" [ref=e85] [cursor=pointer]:
            - /url: "#included"
          - link "What's Excluded" [ref=e86] [cursor=pointer]:
            - /url: "#excluded"
          - link "Meeting Point" [ref=e87] [cursor=pointer]:
            - /url: "#meeting"
          - link "Cancellation Policy" [ref=e88] [cursor=pointer]:
            - /url: "#cancellation"
          - link "Reviews" [ref=e89] [cursor=pointer]:
            - /url: "#reviews"
          - link "More experiences to explore" [ref=e90] [cursor=pointer]:
            - /url: "#related"
      - generic [ref=e91]:
        - article [ref=e92]:
          - generic [ref=e93]:
            - heading "About This Tour" [level=2] [ref=e94]
            - generic [ref=e95]: Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.
          - generic [ref=e96]:
            - heading "Tour facts" [level=2] [ref=e97]
            - generic [ref=e98]:
              - generic [ref=e99]:
                - term [ref=e100]: Duration
                - definition [ref=e101]: 3 hours
              - generic [ref=e102]:
                - term [ref=e103]: Group size
                - definition [ref=e104]: 1–20 travelers
          - generic [ref=e105]:
            - heading "Highlights" [level=2] [ref=e106]
            - list [ref=e107]:
              - listitem [ref=e108]:
                - img [ref=e109]
                - text: Hidden courtyards
              - listitem [ref=e111]:
                - img [ref=e112]
                - text: Local artisan shops
              - listitem [ref=e114]:
                - img [ref=e115]
                - text: Ancient Roman stories
              - listitem [ref=e117]:
                - img [ref=e118]
                - text: Off the beaten path
          - generic [ref=e120]:
            - heading "What's Included" [level=3] [ref=e124]:
              - button "What's Included" [ref=e125]:
                - text: What's Included
                - img [ref=e126]
            - heading "What's Excluded" [level=3] [ref=e131]:
              - button "What's Excluded" [ref=e132]:
                - text: What's Excluded
                - img [ref=e133]
          - generic [ref=e135]:
            - heading "Meeting Point" [level=2] [ref=e136]
            - paragraph [ref=e137]:
              - img [ref=e138]
              - text: Colosseum main entrance
          - generic [ref=e141]:
            - heading "Cancellation Policy" [level=2] [ref=e142]
            - paragraph [ref=e143]: Free cancellation up to 24 hours before the tour.
          - region "Reviews" [ref=e144]:
            - heading "Reviews" [level=2] [ref=e145]
            - generic [ref=e146]:
              - heading "Reviews" [level=3] [ref=e147]
              - generic [ref=e149]:
                - generic [ref=e150]: "4.5"
                - generic [ref=e151]:
                  - 'img "Rating: 4.5 out of 5" [ref=e152]':
                    - img [ref=e153]
                    - img [ref=e155]
                    - img [ref=e157]
                    - img [ref=e159]
                    - img [ref=e161]
                  - paragraph [ref=e163]: 2 reviews
              - generic [ref=e164]:
                - generic [ref=e165]:
                  - generic [ref=e166]:
                    - generic [ref=e167]: Test
                    - generic [ref=e168]: Edited
                  - generic [ref=e169]:
                    - 'img "Rating: 4 out of 5" [ref=e170]':
                      - img [ref=e171]
                      - img [ref=e173]
                      - img [ref=e175]
                      - img [ref=e177]
                      - img [ref=e179]
                    - generic [ref=e181]: September 28, 2026
                  - paragraph [ref=e182]: Updated comment value here.
                - generic [ref=e183]:
                  - generic [ref=e185]: Test
                  - generic [ref=e186]:
                    - 'img "Rating: 5 out of 5" [ref=e187]':
                      - img [ref=e188]
                      - img [ref=e190]
                      - img [ref=e192]
                      - img [ref=e194]
                      - img [ref=e196]
                    - generic [ref=e198]: September 12, 2026
                  - paragraph [ref=e199]: A wonderful hidden-gems walk through Rome — highly recommended!
        - complementary:
          - generic [ref=e201]:
            - paragraph [ref=e202]:
              - generic [ref=e203]: €45.00 / person
              - generic [ref=e204]: 10/17/2026 · 1 · €45.00
            - generic [ref=e205]:
              - link "Continue to booking" [ref=e206] [cursor=pointer]:
                - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
              - button "Edit booking selections" [ref=e207]
      - region "More experiences to explore" [ref=e208]:
        - heading "More experiences to explore" [level=2] [ref=e209]
        - generic [ref=e210]:
          - generic [ref=e211]:
            - 'link "Venice Hidden Canals Walking Tour Next: Oct 17, 2026 Venice, Italy 2.5 hours Venice Hidden Canals Walking Tour Walking Tours €55.00" [ref=e212] [cursor=pointer]':
              - /url: /en/tours/venice-walking-tour-hidden-canals
              - generic [ref=e213]:
                - img "Venice Hidden Canals Walking Tour" [ref=e214]
                - generic [ref=e215]: "Next: Oct 17, 2026"
              - generic [ref=e216]:
                - generic [ref=e217]:
                  - generic [ref=e218]: Venice, Italy
                  - generic [ref=e219]: ·
                  - generic [ref=e220]: 2.5 hours
                - heading "Venice Hidden Canals Walking Tour" [level=2] [ref=e221]
                - generic [ref=e222]:
                  - generic [ref=e223]: Walking Tours
                  - generic [ref=e224]: €55.00
            - button "Save to wishlist" [ref=e226]:
              - generic [ref=e227]: ♡
          - generic [ref=e228]:
            - 'link "Barcelona Gaudí Architecture Masterpieces Tour Next: Oct 17, 2026 Barcelona, Spain 4 hours Barcelona Gaudí Architecture Masterpieces Tour Walking Tours €65.00" [ref=e229] [cursor=pointer]':
              - /url: /en/tours/barcelona-gaudi-architecture-tour
              - generic [ref=e230]:
                - img "Barcelona Gaudí Architecture Masterpieces Tour" [ref=e231]
                - generic [ref=e232]: "Next: Oct 17, 2026"
              - generic [ref=e233]:
                - generic [ref=e234]:
                  - generic [ref=e235]: Barcelona, Spain
                  - generic [ref=e236]: ·
                  - generic [ref=e237]: 4 hours
                - heading "Barcelona Gaudí Architecture Masterpieces Tour" [level=2] [ref=e238]
                - generic [ref=e239]:
                  - generic [ref=e240]: Walking Tours
                  - generic [ref=e241]: €65.00
            - button "Save to wishlist" [ref=e243]:
              - generic [ref=e244]: ♡
          - generic [ref=e245]:
            - 'link "Montmartre Artists Quarter Walking Tour Next: Oct 17, 2026 Paris, France 2.5 hours Montmartre Artists Quarter Walking Tour Walking Tours €49.00" [ref=e246] [cursor=pointer]':
              - /url: /en/tours/paris-montmartre-artists-quarter
              - generic [ref=e247]:
                - img "Montmartre Artists Quarter Walking Tour" [ref=e248]
                - generic [ref=e249]: "Next: Oct 17, 2026"
              - generic [ref=e250]:
                - generic [ref=e251]:
                  - generic [ref=e252]: Paris, France
                  - generic [ref=e253]: ·
                  - generic [ref=e254]: 2.5 hours
                - heading "Montmartre Artists Quarter Walking Tour" [level=2] [ref=e255]
                - generic [ref=e256]:
                  - generic [ref=e257]: Walking Tours
                  - generic [ref=e258]: €49.00
            - button "Save to wishlist" [ref=e260]:
              - generic [ref=e261]: ♡
  - contentinfo [ref=e262]:
    - generic [ref=e263]:
      - generic [ref=e264]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e267]
        - generic [ref=e268]:
          - paragraph [ref=e269]: Explore
          - list [ref=e270]:
            - listitem [ref=e271]:
              - link "Search tours" [ref=e272] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e273]:
              - link "Categories" [ref=e274] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e275]:
              - link "Destinations" [ref=e276] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e277]:
          - paragraph [ref=e278]: Company
          - list [ref=e279]:
            - listitem [ref=e280]:
              - link "Privacy policy" [ref=e281] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e282]:
              - link "Terms of service" [ref=e283] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e284]:
        - generic [ref=e285]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e287]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e288]:
      - generic [ref=e289]: We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies.
      - generic [ref=e290]:
        - button "Reject" [ref=e291]
        - button "Accept" [ref=e292]
  - alert [ref=e293]
```

# Test source

```ts
  126 |     await expect(page.locator('main#main-content')).toBeVisible();
  127 |     if (Array.isArray(es.guide_languages) && es.guide_languages.length > 0) {
  128 |       const guideFact = page.locator('#facts p', { hasText: /en vivo/i });
  129 |       await expect(guideFact).toBeVisible();
  130 |       await expect(guideFact).toContainText(expectedGuideNames(es.guide_languages, 'es').join(', '));
  131 |     }
  132 |   });
  133 | 
  134 |   test('image alt language is separated from page-control language', async ({ page, request }) => {
  135 |     const response = await request.get(`/api/public/tours/${slug}?locale=en`);
  136 |     expect(response.ok()).toBeTruthy();
  137 |     const detail = (await response.json()).data;
  138 |     const images = Array.isArray(detail.images) ? detail.images : [];
  139 |     // This seeded scenario explicitly exercises a populated gallery; empty
  140 |     // galleries have separate reader/browser coverage.
  141 |     expect(images.length).toBeGreaterThan(0);
  142 |     const first = images[0] as { alt?: string | null; alt_locale?: string | null };
  143 |     const actualLang: string = first.alt_locale ?? detail.content_locale ?? 'en';
  144 | 
  145 |     await page.goto('/en/tours/' + slug);
  146 |     await expect(page.locator('main#main-content')).toBeVisible();
  147 |     // Page control uses page language (canonical tile; the legacy single
  148 |     // "Open image gallery" trigger retired with the old engine) ...
  149 |     const trigger = page.getByRole('button', { name: 'View image 1' });
  150 |     await expect(trigger).toBeVisible();
  151 |     // ... while the image itself declares its actual language (authored alt
  152 |     // locale, else the selected content locale; title fallback keeps meaning).
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
> 226 |           expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
      |                                                                         ^ Error: expect(received).toEqual(expected) // deep equality
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
  243 |       const overview = page.locator('main#main-content #overview');
  244 |       await expect(overview).toBeVisible();
  245 |       // Preview first: the full text is reachable only through the disclosure.
  246 |       await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
  247 |       const expand = overview.getByRole('button', { name: /^(Read more|Read less)$/ });
  248 |       await expect(expand).toHaveAttribute('aria-expanded', 'false');
  249 |       expect(await expand.getAttribute('aria-controls')).toBeTruthy();
  250 |       await expand.focus();
  251 |       await expect(expand).toBeFocused();
  252 |       await page.keyboard.press('Enter');
  253 |       await expect(expand).toHaveAttribute('aria-expanded', 'true');
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
  264 |   test('page stays readable and operable with reduced motion preferred', async ({ page, request }) => {
  265 |     const response = await request.get(`/api/public/tours/${slug}?locale=es`);
  266 |     expect(response.ok()).toBeTruthy();
  267 |     await page.emulateMedia({ reducedMotion: 'reduce' });
  268 |     await page.goto('/es/tours/' + slug);
  269 |     await expect(page.locator('main#main-content')).toBeVisible();
  270 |     await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  271 |     const nav = page.getByRole('navigation', { name: 'Secciones del tour', exact: true });
  272 |     await expect(nav).toBeVisible();
  273 |     expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  274 |   });
  275 | });
  276 | 
  277 | // Spec 020 US5: signed-out wishlist prompt uses the shared dialog with its
  278 | // localized description; focus, Escape and trigger return hold with no axe
  279 | // violations. Authenticated save/fail/denied outcomes are covered by the
  280 | // authed wishlist suite; this slice claims only the signed-out modal.
  281 | test.describe('Spec020 US5 wishlist prompt dialog', () => {
  282 |   const slug = 'hidden-gems-rome-walking-tour';
  283 | 
  284 |   test('sign-in prompt dialog is described, modal and focus-safe with no axe violations', async ({
  285 |     page,
  286 |   }) => {
  287 |     await page.goto('/en/tours/' + slug);
  288 |     await expect(page.locator('main#main-content')).toBeVisible();
  289 |     const trigger = page.locator('main#main-content [data-testid="wishlist-button"]').first();
  290 |     await expect(trigger).toBeVisible();
  291 |     await expect(trigger).toBeEnabled();
  292 |     await trigger.focus();
  293 |     await trigger.click();
  294 | 
  295 |     const dialog = page.getByRole('dialog', { name: 'Sign in to save tours', exact: true });
  296 |     await expect(dialog).toBeVisible();
  297 |     const describedBy = await dialog.getAttribute('aria-describedby');
  298 |     expect(typeof describedBy === 'string' && describedBy.length > 0).toBe(true);
  299 |     await expect(dialog.locator(`[id="${describedBy}"]`)).toHaveText(
  300 |       'Sign in or create an account to add tours to your wishlist.',
  301 |     );
  302 |     // Focus moves into the dialog close control on open.
  303 |     await expect(page.getByRole('button', { name: 'Not now' }).first()).toBeFocused();
  304 |     expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  305 | 
  306 |     await page.keyboard.press('Escape');
  307 |     await expect(dialog).toHaveCount(0);
  308 |     await expect(trigger).toBeFocused();
  309 |   });
  310 | });
  311 | 
```