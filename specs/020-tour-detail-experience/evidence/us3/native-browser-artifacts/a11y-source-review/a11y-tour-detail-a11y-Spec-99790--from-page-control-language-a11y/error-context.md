# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: a11y\tour-detail-a11y.spec.ts >> Spec019 tour content language and media controls >> image alt language is separated from page-control language
- Location: tests\e2e\a11y\tour-detail-a11y.spec.ts:134:7

# Error details

```
Error: expect(locator).toHaveAttribute(expected) failed

Locator:  locator('main#main-content img').first()
Expected: "en"
Received: ""
Timeout:  5000ms

Call log:
  - Expect "toHaveAttribute" with timeout 5000ms
  - waiting for locator('main#main-content img').first()
    14 × locator resolved to <img loading="lazy" decoding="async" data-nimg="fill" alt="Venice Hidden Canals Walking Tour" sizes="(min-width: 768px) 33vw, (min-width: 640px) 50vw, 100vw" class="object-cover transition-transform duration-300 group-hover:scale-105" src="/_next/image?url=http%3A%2F%2Fcdn.bookly.test%2Ftours%2Fvenice-walking.jpg&w=3840&q=75" srcset="/_next/image?url=http%3A%2F%2Fcdn.bookly.test%2Ftours%2Fvenice-walking.jpg&w=256&q=75 256w, /_next/image?url=http%3A%2F%2Fcdn.bookly.test%2Ftours%2Fvenice-walking.jpg&…/>
       - unexpected value "null"

```

```yaml
- img "Venice Hidden Canals Walking Tour"
```

# Test source

```ts
  55  |     const response = await request.get(`/api/public/tours/${slug}?locale=en`);
  56  |     expect(response.ok()).toBeTruthy();
  57  |     for (const width of [390, 768, 1024, 1440]) {
  58  |       await page.setViewportSize({ width, height: 900 });
  59  |       await page.goto('/en/tours/' + slug);
  60  |       await expect(page.locator('main#main-content')).toBeVisible();
  61  |       const results = await new AxeBuilder({ page }).analyze();
  62  |       expect(results.violations).toEqual([]);
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
> 155 |     await expect(galleryImg).toHaveAttribute('lang', actualLang);
      |                              ^ Error: expect(locator).toHaveAttribute(expected) failed
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
```