# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: a11y\tour-detail-a11y.spec.ts >> Spec020 US6 locale, width and interaction acceptance >> page stays readable and operable with reduced motion preferred
- Location: tests\e2e\a11y\tour-detail-a11y.spec.ts:265:7

# Error details

```
Error: expect(received).toEqual(expected) // deep equality

- Expected  -  1
+ Received  + 58

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
+               "bgColor": "#f7f9fb",
+               "contrastRatio": 4.44,
+               "expectedContrastRatio": "4.5:1",
+               "fgColor": "#11845b",
+               "fontSize": "10.5pt (14px)",
+               "fontWeight": "normal",
+               "messageKey": null,
+             },
+             "id": "color-contrast",
+             "impact": "serious",
+             "message": "Element has insufficient color contrast of 4.44 (foreground color: #11845b, background color: #f7f9fb, font size: 10.5pt (14px), font weight: normal). Expected contrast ratio of 4.5:1",
+             "relatedNodes": Array [
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
+   Element has insufficient color contrast of 4.44 (foreground color: #11845b, background color: #f7f9fb, font size: 10.5pt (14px), font weight: normal). Expected contrast ratio of 4.5:1",
+         "html": "<span aria-hidden=\"true\">4.5</span>",
+         "impact": "serious",
+         "none": Array [],
+         "target": Array [
+           "span[aria-label=\"Valoración: 4.5 de 5\"] > span:nth-child(2)",
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
    - link "Saltar al contenido principal" [ref=e3] [cursor=pointer]:
      - /url: "#main-content"
    - generic [ref=e4]:
      - link "Bookly" [ref=e5] [cursor=pointer]:
        - /url: /es
      - navigation "Navegacion principal" [ref=e6]:
        - link "Inicio" [ref=e7] [cursor=pointer]:
          - /url: /es
        - link "Buscar tours" [ref=e8] [cursor=pointer]:
          - /url: /es/search
        - link "Categorías" [ref=e9] [cursor=pointer]:
          - /url: /es/categories
        - link "Destinos" [ref=e10] [cursor=pointer]:
          - /url: /es/destinations
      - generic [ref=e11]:
        - button "Buscar tours" [ref=e12]:
          - img [ref=e13]
        - combobox "Cambiar idioma" [ref=e18]:
          - option "English"
          - option "Español" [selected]
          - option "Italiano"
        - link "Iniciar sesion" [ref=e19] [cursor=pointer]:
          - /url: /es/auth/login
          - generic [ref=e20]: Iniciar sesion
        - link "Registrarse" [ref=e21] [cursor=pointer]:
          - /url: /es/auth/register
  - main [ref=e22]:
    - generic [ref=e23]:
      - generic [ref=e24]:
        - navigation "Ruta de navegación" [ref=e25]:
          - list [ref=e26]:
            - listitem [ref=e27]:
              - link "Inicio" [ref=e28] [cursor=pointer]:
                - /url: /es
            - listitem [ref=e29]:
              - generic [ref=e30]: /
              - link "Walking Tours" [ref=e31] [cursor=pointer]:
                - /url: /es/categories/walking
            - listitem [ref=e32]:
              - generic [ref=e33]: /
              - generic [ref=e34]: Tour a pie por las joyas ocultas de Roma
        - heading "Tour a pie por las joyas ocultas de Roma" [level=1] [ref=e35]
        - generic [ref=e37]:
          - img [ref=e38]
          - text: Rome, Italy
        - generic [ref=e41]:
          - 'generic "Valoración: 4.5 de 5" [ref=e42]':
            - img [ref=e43]
            - generic [ref=e45]: "4.5"
            - generic [ref=e46]: "Valoración: 4.5 de 5"
          - link "(2 Reseñas)" [ref=e47] [cursor=pointer]:
            - /url: "#reviews"
      - generic [ref=e48]:
        - generic [ref=e49]:
          - button "Ver imagen 1" [ref=e50]:
            - generic [ref=e51]:
              - img [ref=e52]
              - generic [ref=e54]: Esta foto no está disponible.
          - button "Ver imagen 2" [ref=e55]:
            - generic [ref=e56]:
              - img [ref=e57]
              - generic [ref=e59]: Esta foto no está disponible.
          - button "Ver imagen 3" [ref=e60]:
            - generic [ref=e61]:
              - img [ref=e62]
              - generic [ref=e64]: Esta foto no está disponible.
          - button "Ver imagen 4" [ref=e65]:
            - generic [ref=e66]:
              - img [ref=e67]
              - generic [ref=e69]: Esta foto no está disponible.
          - button "Ver imagen 5" [ref=e70]:
            - generic [ref=e71]:
              - img [ref=e72]
              - generic [ref=e74]: Esta foto no está disponible.
        - button "Ver las 5 fotos" [ref=e75]
      - navigation "Secciones del tour" [ref=e76]:
        - generic [ref=e77]:
          - link "Sobre Este Tour" [ref=e78] [cursor=pointer]:
            - /url: "#overview"
          - link "Datos del tour" [ref=e79] [cursor=pointer]:
            - /url: "#facts"
          - link "Destacados" [ref=e80] [cursor=pointer]:
            - /url: "#highlights"
          - link "Qué Incluye" [ref=e81] [cursor=pointer]:
            - /url: "#included"
          - link "Qué No Incluye" [ref=e82] [cursor=pointer]:
            - /url: "#excluded"
          - link "Punto de Encuentro" [ref=e83] [cursor=pointer]:
            - /url: "#meeting"
          - link "Política de Cancelación" [ref=e84] [cursor=pointer]:
            - /url: "#cancellation"
          - link "Reseñas" [ref=e85] [cursor=pointer]:
            - /url: "#reviews"
      - generic [ref=e86]:
        - article [ref=e87]:
          - generic [ref=e88]:
            - heading "Sobre Este Tour" [level=2] [ref=e89]
            - generic [ref=e90]: Descubre los rincones secretos de Roma que la mayoría de los turistas nunca ven. Desde patios ocultos hasta tiendas de artesanos locales, este tour te lleva fuera de los caminos trillados a través de la Ciudad Eterna.
          - generic [ref=e91]:
            - heading "Datos del tour" [level=2] [ref=e92]
            - generic [ref=e93]:
              - generic [ref=e94]:
                - term [ref=e95]: Duración
                - definition [ref=e96]: 3 hours
              - generic [ref=e97]:
                - term [ref=e98]: Tamaño del grupo
                - definition [ref=e99]: 1–20 viajeros
          - generic [ref=e100]:
            - heading "Destacados" [level=2] [ref=e101]
            - list [ref=e102]:
              - listitem [ref=e103]:
                - img [ref=e104]
                - text: Patios ocultos
              - listitem [ref=e106]:
                - img [ref=e107]
                - text: Tiendas de artesanos locales
              - listitem [ref=e109]:
                - img [ref=e110]
                - text: Antiguas historias romanas
              - listitem [ref=e112]:
                - img [ref=e113]
                - text: Fuera de los caminos trillados
          - generic [ref=e115]:
            - heading "Qué Incluye" [level=3] [ref=e119]:
              - button "Qué Incluye" [ref=e120]:
                - text: Qué Incluye
                - img [ref=e121]
            - heading "Qué No Incluye" [level=3] [ref=e126]:
              - button "Qué No Incluye" [ref=e127]:
                - text: Qué No Incluye
                - img [ref=e128]
          - generic [ref=e130]:
            - heading "Punto de Encuentro" [level=2] [ref=e131]
            - paragraph [ref=e132]:
              - img [ref=e133]
              - text: Entrada principal del Coliseo
          - generic [ref=e136]:
            - heading "Política de Cancelación" [level=2] [ref=e137]
            - paragraph [ref=e138]: Cancelación gratuita hasta 24 horas antes del tour.
          - generic [ref=e140]:
            - heading "Reseñas" [level=3] [ref=e141]
            - generic [ref=e143]:
              - generic "Average rating 4.5 out of 5" [ref=e144]: "4.5"
              - generic [ref=e145]:
                - radiogroup "Star rating" [ref=e146]:
                  - radio "1 star" [disabled] [ref=e147]:
                    - img [ref=e148]
                  - radio "2 stars" [disabled] [ref=e150]:
                    - img [ref=e151]
                  - radio "3 stars" [disabled] [ref=e153]:
                    - img [ref=e154]
                  - radio "4 stars" [disabled] [ref=e156]:
                    - img [ref=e157]
                  - radio "5 stars" [checked] [disabled] [ref=e159]:
                    - img [ref=e160]
                - paragraph [ref=e162]: 2 reseñas
            - generic [ref=e163]:
              - generic [ref=e164]:
                - generic [ref=e165]:
                  - generic [ref=e166]: Test
                  - generic [ref=e167]: Edited
                - generic [ref=e168]:
                  - radiogroup "Star rating" [ref=e169]:
                    - radio "1 star" [disabled] [ref=e170]:
                      - img [ref=e171]
                    - radio "2 stars" [disabled] [ref=e173]:
                      - img [ref=e174]
                    - radio "3 stars" [disabled] [ref=e176]:
                      - img [ref=e177]
                    - radio "4 stars" [checked] [disabled] [ref=e179]:
                      - img [ref=e180]
                    - radio "5 stars" [disabled] [ref=e182]:
                      - img [ref=e183]
                  - generic [ref=e185]: 28 de septiembre de 2026
                - paragraph [ref=e186]: Updated comment value here.
              - generic [ref=e187]:
                - generic [ref=e189]: Test
                - generic [ref=e190]:
                  - radiogroup "Star rating" [ref=e191]:
                    - radio "1 star" [disabled] [ref=e192]:
                      - img [ref=e193]
                    - radio "2 stars" [disabled] [ref=e195]:
                      - img [ref=e196]
                    - radio "3 stars" [disabled] [ref=e198]:
                      - img [ref=e199]
                    - radio "4 stars" [disabled] [ref=e201]:
                      - img [ref=e202]
                    - radio "5 stars" [checked] [disabled] [ref=e204]:
                      - img [ref=e205]
                  - generic [ref=e207]: 12 de septiembre de 2026
                - paragraph [ref=e208]: A wonderful hidden-gems walk through Rome — highly recommended!
        - complementary [ref=e209]:
          - generic [ref=e211]:
            - generic [ref=e212]:
              - generic [ref=e213]:
                - text: 45,00 €
                - generic [ref=e214]: / persona
              - button "Guardar en lista de deseos" [ref=e215]:
                - generic [ref=e216]: ♡
            - generic [ref=e217]:
              - generic [ref=e218]: Seleccionar Fecha
              - generic [ref=e219]:
                - paragraph [ref=e220]: Próximo disponible
                - paragraph [ref=e221]: 17/10/2026
              - combobox "Seleccionar Fecha" [ref=e222]:
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
            - generic [ref=e223]:
              - generic [ref=e224]: Participantes
              - group "Participantes" [ref=e225]:
                - button "Reducir participantes" [disabled] [ref=e226]: "-"
                - status [ref=e227]: "1"
                - button "Aumentar participantes" [ref=e228]: +
                - generic [ref=e229]: 1–20 permitidos
            - paragraph [ref=e230]: "Total estimado: 45,00 €"
            - paragraph [ref=e231]: Cancelación gratuita hasta 24 horas antes del tour.
            - generic [ref=e232]:
              - paragraph [ref=e233]: Inicia sesión para continuar con la reserva. Tu tour, fecha, número de viajeros e idioma se conservan y no se reserva nada hasta que confirmes.
              - link "Reservar Ahora" [ref=e234] [cursor=pointer]:
                - /url: /es/auth/login?returnUrl=%2Fes%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
              - link "Crear cuenta" [ref=e235] [cursor=pointer]:
                - /url: /es/auth/register?returnUrl=%2Fes%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
  - contentinfo [ref=e236]:
    - generic [ref=e237]:
      - generic [ref=e238]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e241]
        - generic [ref=e242]:
          - paragraph [ref=e243]: Explorar
          - list [ref=e244]:
            - listitem [ref=e245]:
              - link "Buscar tours" [ref=e246] [cursor=pointer]:
                - /url: /es/search
            - listitem [ref=e247]:
              - link "Categorías" [ref=e248] [cursor=pointer]:
                - /url: /es/categories
            - listitem [ref=e249]:
              - link "Destinos" [ref=e250] [cursor=pointer]:
                - /url: /es/destinations
        - generic [ref=e251]:
          - paragraph [ref=e252]: Empresa
          - list [ref=e253]:
            - listitem [ref=e254]:
              - link "Política de privacidad" [ref=e255] [cursor=pointer]:
                - /url: /es/privacy
            - listitem [ref=e256]:
              - link "Términos del servicio" [ref=e257] [cursor=pointer]:
                - /url: /es/terms
      - generic [ref=e258]:
        - generic [ref=e259]: © 2026 Bookly. Todos los derechos reservados.
        - combobox "Cambiar idioma" [ref=e261]:
          - option "English"
          - option "Español" [selected]
          - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e262]:
      - generic [ref=e263]: Usamos cookies para mejorar tu experiencia de navegación y analizar nuestro tráfico. Al hacer clic en "Aceptar", aceptas nuestro uso de cookies.
      - generic [ref=e264]:
        - button "Rechazar" [ref=e265]
        - button "Aceptar" [ref=e266]
  - alert [ref=e267]
```

# Test source

```ts
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
> 274 |     expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
      |                                                                   ^ Error: expect(received).toEqual(expected) // deep equality
  275 |   });
  276 | });
  277 | 
```