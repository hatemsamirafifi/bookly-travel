# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: a11y\tour-detail-a11y.spec.ts >> Spec020 US6 locale, width and interaction acceptance >> page stays readable and operable with reduced motion preferred
- Location: tests\e2e\a11y\tour-detail-a11y.spec.ts:265:7

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('navigation', { name: /secciones|Tour sections/ })
Expected: visible
Timeout: 5000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 5000ms
  - waiting for getByRole('navigation', { name: /secciones|Tour sections/ })

```

```yaml
- banner:
  - link "Saltar al contenido principal":
    - /url: "#main-content"
  - link "Bookly":
    - /url: /es
  - navigation "Navegacion principal":
    - link "Inicio":
      - /url: /es
    - link "Buscar tours":
      - /url: /es/search
    - link "Categorías":
      - /url: /es/categories
    - link "Destinos":
      - /url: /es/destinations
  - button "Buscar tours"
  - combobox "Cambiar idioma":
    - option "English"
    - option "Español" [selected]
    - option "Italiano"
  - link "Iniciar sesion":
    - /url: /es/auth/login
  - link "Registrarse":
    - /url: /es/auth/register
- main:
  - navigation "Ruta de navegación":
    - list:
      - listitem:
        - link "Inicio":
          - /url: /es
      - listitem:
        - link "Walking Tours":
          - /url: /es/categories/walking
      - listitem: Tour a pie por las joyas ocultas de Roma
  - heading "Tour a pie por las joyas ocultas de Roma" [level=1]
  - text: "Rome, Italy Valoración: 4.5 de 5"
  - link "(2 Reseñas)":
    - /url: "#reviews"
  - button "Ver imagen 1": Esta foto no está disponible.
  - button "Ver imagen 2": Esta foto no está disponible.
  - button "Ver imagen 3": Esta foto no está disponible.
  - button "Ver imagen 4": Esta foto no está disponible.
  - button "Ver imagen 5": Esta foto no está disponible.
  - button "Ver las 5 fotos"
  - navigation "Secciones del tour":
    - link "Sobre Este Tour":
      - /url: "#overview"
    - link "Datos del tour":
      - /url: "#facts"
    - link "Destacados":
      - /url: "#highlights"
    - link "Qué Incluye":
      - /url: "#included"
    - link "Qué No Incluye":
      - /url: "#excluded"
    - link "Punto de Encuentro":
      - /url: "#meeting"
    - link "Política de Cancelación":
      - /url: "#cancellation"
    - link "Reseñas":
      - /url: "#reviews"
  - article:
    - heading "Sobre Este Tour" [level=2]
    - text: Descubre los rincones secretos de Roma que la mayoría de los turistas nunca ven. Desde patios ocultos hasta tiendas de artesanos locales, este tour te lleva fuera de los caminos trillados a través de la Ciudad Eterna.
    - heading "Datos del tour" [level=2]
    - term: Duración
    - definition: 3 hours
    - term: Tamaño del grupo
    - definition: 1–20 viajeros
    - heading "Destacados" [level=2]
    - list:
      - listitem: Patios ocultos
      - listitem: Tiendas de artesanos locales
      - listitem: Antiguas historias romanas
      - listitem: Fuera de los caminos trillados
    - heading "Qué Incluye" [level=3]:
      - button "Qué Incluye"
    - heading "Qué No Incluye" [level=3]:
      - button "Qué No Incluye"
    - heading "Punto de Encuentro" [level=2]
    - paragraph: Entrada principal del Coliseo
    - heading "Política de Cancelación" [level=2]
    - paragraph: Cancelación gratuita hasta 24 horas antes del tour.
    - heading "Reseñas" [level=3]
    - text: "4.5"
    - radiogroup "Star rating":
      - radio "1 star" [disabled]
      - radio "2 stars" [disabled]
      - radio "3 stars" [disabled]
      - radio "4 stars" [disabled]
      - radio "5 stars" [checked] [disabled]
    - paragraph: 2 reseñas
    - text: Test Edited
    - radiogroup "Star rating":
      - radio "1 star" [disabled]
      - radio "2 stars" [disabled]
      - radio "3 stars" [disabled]
      - radio "4 stars" [checked] [disabled]
      - radio "5 stars" [disabled]
    - text: 28 de septiembre de 2026
    - paragraph: Updated comment value here.
    - text: Test
    - radiogroup "Star rating":
      - radio "1 star" [disabled]
      - radio "2 stars" [disabled]
      - radio "3 stars" [disabled]
      - radio "4 stars" [disabled]
      - radio "5 stars" [checked] [disabled]
    - text: 12 de septiembre de 2026
    - paragraph: A wonderful hidden-gems walk through Rome — highly recommended!
  - complementary:
    - text: 45,00 € / persona
    - button "Guardar en lista de deseos"
    - text: Seleccionar Fecha
    - paragraph: Próximo disponible
    - paragraph: 17/10/2026
    - combobox "Seleccionar Fecha":
      - option "Seleccionar Fecha"
      - option "17/10/2026" [selected]
      - option "18/10/2026"
      - option "19/10/2026"
      - option "20/10/2026"
      - option "21/10/2026"
      - option "22/10/2026"
      - option "23/10/2026"
      - option "24/10/2026"
      - option "25/10/2026"
      - option "26/10/2026"
      - option "27/10/2026"
      - option "28/10/2026"
      - option "29/10/2026"
      - option "30/10/2026"
      - option "31/10/2026"
      - option "01/11/2026"
      - option "02/11/2026"
      - option "03/11/2026"
      - option "04/11/2026"
      - option "05/11/2026"
      - option "06/11/2026"
      - option "07/11/2026"
      - option "08/11/2026"
      - option "09/11/2026"
      - option "10/11/2026"
      - option "11/11/2026"
      - option "12/11/2026"
      - option "13/11/2026"
      - option "14/11/2026"
      - option "15/11/2026"
      - option "16/11/2026"
      - option "17/11/2026"
      - option "18/11/2026"
      - option "19/11/2026"
      - option "20/11/2026"
      - option "21/11/2026"
      - option "22/11/2026"
      - option "23/11/2026"
      - option "24/11/2026"
      - option "25/11/2026"
      - option "26/11/2026"
      - option "27/11/2026"
      - option "28/11/2026"
      - option "29/11/2026"
      - option "30/11/2026"
      - option "01/12/2026"
      - option "02/12/2026"
      - option "03/12/2026"
      - option "04/12/2026"
      - option "05/12/2026"
      - option "06/12/2026"
      - option "07/12/2026"
      - option "08/12/2026"
      - option "09/12/2026"
      - option "10/12/2026"
      - option "11/12/2026"
      - option "12/12/2026"
      - option "13/12/2026"
      - option "14/12/2026"
    - text: Participantes
    - group "Participantes":
      - button "Reducir participantes" [disabled]: "-"
      - status: "1"
      - button "Aumentar participantes": +
      - text: 1–20 permitidos
    - paragraph: "Total estimado: 45,00 €"
    - paragraph: Cancelación gratuita hasta 24 horas antes del tour.
    - paragraph: Inicia sesión para continuar con la reserva. Tu tour, fecha, número de viajeros e idioma se conservan y no se reserva nada hasta que confirmes.
    - link "Reservar Ahora":
      - /url: /es/auth/login?returnUrl=%2Fes%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
    - link "Crear cuenta":
      - /url: /es/auth/register?returnUrl=%2Fes%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
- contentinfo:
  - img "Bookly Travel — Book smarter. Travel better."
  - paragraph: Explorar
  - list:
    - listitem:
      - link "Buscar tours":
        - /url: /es/search
    - listitem:
      - link "Categorías":
        - /url: /es/categories
    - listitem:
      - link "Destinos":
        - /url: /es/destinations
  - paragraph: Empresa
  - list:
    - listitem:
      - link "Política de privacidad":
        - /url: /es/privacy
    - listitem:
      - link "Términos del servicio":
        - /url: /es/terms
  - text: © 2026 Bookly. Todos los derechos reservados.
  - combobox "Cambiar idioma":
    - option "English"
    - option "Español" [selected]
    - option "Italiano"
- complementary "Cookie consent":
  - text: Usamos cookies para mejorar tu experiencia de navegación y analizar nuestro tráfico. Al hacer clic en "Aceptar", aceptas nuestro uso de cookies.
  - button "Rechazar"
  - button "Aceptar"
- alert
```

# Test source

```ts
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
  272 |     const nav = page.getByRole('navigation', { name: /secciones|Tour sections/ });
> 273 |     await expect(nav).toBeVisible();
      |                       ^ Error: expect(locator).toBeVisible() failed
  274 |     expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  275 |   });
  276 | });
  277 | 
```