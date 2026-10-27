# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: a11y\tour-detail-a11y.spec.ts >> Spec020 US6 locale, width and interaction acceptance >> long-overview disclosure is fully keyboard operable with announced state
- Location: tests\e2e\a11y\tour-detail-a11y.spec.ts:232:7

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('main#main-content')
Expected: visible
Timeout: 5000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 5000ms
  - waiting for locator('main#main-content')

```

```yaml
- heading "Something went wrong" [level=2]
- paragraph: We've been notified and are working to fix the issue.
- button "Reload page"
- alert
```

# Test source

```ts
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
> 242 |       await expect(page.locator('main#main-content')).toBeVisible();
      |                                                       ^ Error: expect(locator).toBeVisible() failed
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
  264 | 
  265 |   test('page stays readable and operable with reduced motion preferred', async ({ page, request }) => {
  266 |     const response = await request.get(`/api/public/tours/${slug}?locale=es`);
  267 |     expect(response.ok()).toBeTruthy();
  268 |     await page.emulateMedia({ reducedMotion: 'reduce' });
  269 |     await page.goto('/es/tours/' + slug);
  270 |     await expect(page.locator('main#main-content')).toBeVisible();
  271 |     await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  272 |     const nav = page.getByRole('navigation', { name: 'Secciones del tour', exact: true });
  273 |     await expect(nav).toBeVisible();
  274 |     expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  275 |   });
  276 | });
  277 | 
```