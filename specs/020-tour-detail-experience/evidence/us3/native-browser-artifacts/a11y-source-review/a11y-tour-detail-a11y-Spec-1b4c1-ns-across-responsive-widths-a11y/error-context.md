# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: a11y\tour-detail-a11y.spec.ts >> Spec019 tour content language and media controls >> en tour detail has no axe violations across responsive widths
- Location: tests\e2e\a11y\tour-detail-a11y.spec.ts:54:7

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
  1   | import { test, expect } from '@playwright/test';
  2   | import AxeBuilder from '@axe-core/playwright';
  3   | import { startSsrHarness } from '../helpers/tour-detail-ssr';
  4   | import { createFullTourDetail } from '../../fixtures/tour-detail';
  5   | 
  6   | for (const [locale, title, retry] of [
  7   |   ['en', 'This tour could not be loaded', 'Try again'],
  8   |   ['es', 'No se pudo cargar este tour', 'Volver a intentar'],
  9   |   ['it', 'Impossibile caricare questo tour', 'Riprova'],
  10  | ]) {
  11  |   test(`${locale} recoverable tour failure has a page heading and accessible retry`, async ({ page }) => {
  12  |     test.setTimeout(180000);
  13  |     await page.setViewportSize({ width: 390, height: 844 });
  14  |     const slug = 'hidden-gems-rome-walking-tour';
  15  |     const harness = await startSsrHarness(slug, () => ({
  16  |       status: 503,
  17  |       body: { message: 'PRIVATE-UPSTREAM-TEXT' },
  18  |     }));
  19  |     try {
  20  |       await page.goto(`http://127.0.0.1:${harness.nextPort}/${locale}/tours/${slug}`);
  21  |       await expect(page.getByRole('heading', { level: 1, name: title, exact: true })).toBeVisible();
  22  |       await expect(page.getByRole('button', { name: retry, exact: true })).toBeEnabled();
  23  |       await expect(page.locator('main#main-content')).not.toContainText('PRIVATE-UPSTREAM-TEXT');
  24  |       expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  25  |     } finally {
  26  |       await harness.stop();
  27  |     }
  28  |   });
  29  | }
  30  | 
  31  | for (const locale of ['en', 'es', 'it']) {
  32  |   test(`${locale} tour detail with loaded reviews should not have accessibility violations`, async ({ page, request }) => {
  33  |     const slug = 'hidden-gems-rome-walking-tour';
  34  |     const response = await request.get(`/api/public/tours/${slug}/reviews?page=1&per_page=5`);
  35  |     expect(response.ok()).toBeTruthy();
  36  |     const { data } = await response.json();
  37  |     expect(data.length).toBeGreaterThan(0);
  38  |     await page.goto(`/${locale}/tours/${slug}`);
  39  |     await expect(page.locator('main#main-content')).toBeVisible();
  40  |     // Wait for real API-backed review text, not the initial loading placeholders.
  41  |     await expect(page.locator('#reviews').getByText(data[0].reviewer_name, { exact: true }).first()).toBeVisible();
  42  |     const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
  43  |     expect(accessibilityScanResults.violations).toEqual([]);
  44  |   });
  45  | }
  46  | 
  47  | // Spec019 T073 / FR-030 additions (author-only): affected TourDetail/TourGallery
  48  | // facts only. Real backend + real SSR page, no route mocks, no Axe exclusions.
  49  | // Full EN/ES/IT x 390/768/1024/1440(+320) cartesian is left for ROOT manual;
  50  | // widths and locales are distributed below without duplicated exhaustive loops.
  51  | test.describe('Spec019 tour content language and media controls', () => {
  52  |   const slug = 'hidden-gems-rome-walking-tour';
  53  | 
  54  |   test('en tour detail has no axe violations across responsive widths', async ({ page, request }) => {
  55  |     const response = await request.get(`/api/public/tours/${slug}?locale=en`);
  56  |     expect(response.ok()).toBeTruthy();
  57  |     for (const width of [390, 768, 1024, 1440]) {
  58  |       await page.setViewportSize({ width, height: 900 });
  59  |       await page.goto('/en/tours/' + slug);
  60  |       await expect(page.locator('main#main-content')).toBeVisible();
  61  |       const results = await new AxeBuilder({ page }).analyze();
> 62  |       expect(results.violations).toEqual([]);
      |                                  ^ Error: expect(received).toEqual(expected) // deep equality
  63  |     }
  64  |   });
  65  | 
  66  |   test('gallery layout width has no axe violations', async ({ page }) => {
  67  |     // 320 is layout-relevant for the gallery rail.
  68  |     await page.setViewportSize({ width: 320, height: 900 });
  69  |     await page.goto('/en/tours/' + slug);
  70  |     await expect(page.locator('main#main-content')).toBeVisible();
  71  |     const results = await new AxeBuilder({ page }).analyze();
  72  |     expect(results.violations).toEqual([]);
  73  |   });
  74  | 
  75  |   test('es and it tour detail have no axe violations at spot widths', async ({ page }) => {
  76  |     await page.setViewportSize({ width: 390, height: 844 });
  77  |     await page.goto('/es/tours/' + slug);
  78  |     await expect(page.locator('main#main-content')).toBeVisible();
  79  |     expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  80  | 
  81  |     await page.setViewportSize({ width: 1440, height: 900 });
  82  |     await page.goto('/it/tours/' + slug);
  83  |     await expect(page.locator('main#main-content')).toBeVisible();
  84  |     expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  85  |   });
  86  | 
  87  |   test('difficulty and guide languages are labeled; codes stay stable across page language', async ({
  88  |     page,
  89  |     request,
  90  |   }) => {
  91  |     const readDetail = async (locale: string) => {
  92  |       const response = await request.get(`/api/public/tours/${slug}?locale=${locale}`);
  93  |       expect(response.ok()).toBeTruthy();
  94  |       return (await response.json()).data;
  95  |     };
  96  |     // Spoken guide-language names mirror the production localizer so the
  97  |     // assertion proves the rendered localized fact, not a raw-code substring.
  98  |     const expectedGuideNames = (codes: string[], locale: string) =>
  99  |       codes.map((code) => {
  100 |         const name = new Intl.DisplayNames([locale], { type: 'language' }).of(code) ?? code;
  101 |         return name.charAt(0).toLocaleUpperCase(locale) + name.slice(1);
  102 |       });
  103 |     const en = await readDetail('en');
  104 |     const es = await readDetail('es');
  105 |     // Spoken guide-language codes are tour-level facts: page language must not change them.
  106 |     expect(es.guide_languages).toEqual(en.guide_languages);
  107 | 
  108 |     await page.goto('/en/tours/' + slug);
  109 |     await expect(page.locator('main#main-content')).toBeVisible();
  110 |     if (Array.isArray(en.guide_languages) && en.guide_languages.length > 0) {
  111 |       // Labeled fact in page language with the actual localized spoken names.
  112 |       const guideFact = page.locator('#facts p', { hasText: 'Live guide' });
  113 |       await expect(guideFact).toBeVisible();
  114 |       await expect(guideFact).toContainText(expectedGuideNames(en.guide_languages, 'en').join(', '));
  115 |     }
  116 |     if (typeof en.difficulty_level === 'string' && en.difficulty_level.length > 0) {
  117 |       await expect(page.locator('dt', { hasText: 'Difficulty' }).first()).toBeVisible();
  118 |       await expect(page.locator('#facts dd').filter({ hasText: /.+/ }).first()).toBeVisible();
  119 |     } else {
  120 |       // Older tours without difficulty stay usable: no invented difficulty value.
  121 |       await expect(page.locator('dt', { hasText: 'Difficulty' })).toHaveCount(0);
  122 |     }
  123 | 
  124 |     // Same facts in Spanish: label and spoken names follow page language, codes unchanged.
  125 |     await page.goto('/es/tours/' + slug);
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
```