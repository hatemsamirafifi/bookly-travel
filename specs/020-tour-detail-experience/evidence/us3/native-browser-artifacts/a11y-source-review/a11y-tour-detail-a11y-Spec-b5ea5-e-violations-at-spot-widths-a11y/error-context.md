# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: a11y\tour-detail-a11y.spec.ts >> Spec019 tour content language and media controls >> es and it tour detail have no axe violations at spot widths
- Location: tests\e2e\a11y\tour-detail-a11y.spec.ts:75:7

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
+                 "html": "<p aria-live=\"polite\" class=\"absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-overlay px-3 py-1 text-xs font-medium text-white lg:hidden\">1 de 5</p>",
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
+         "html": "<p aria-live=\"polite\" class=\"absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-overlay px-3 py-1 text-xs font-medium text-white lg:hidden\">1 de 5</p>",
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
    - link "Saltar al contenido principal" [ref=e3] [cursor=pointer]:
      - /url: "#main-content"
    - generic [ref=e4]:
      - link "Bookly" [ref=e5] [cursor=pointer]:
        - /url: /es
      - generic [ref=e6]:
        - button "Buscar tours" [ref=e7]:
          - img [ref=e8]
        - link "Iniciar sesion" [ref=e11] [cursor=pointer]:
          - /url: /es/auth/login
          - img [ref=e12]
        - button "Abrir menu" [ref=e15]:
          - img [ref=e16]
  - main [ref=e17]:
    - generic [ref=e18]:
      - generic [ref=e19]:
        - navigation "Ruta de navegación" [ref=e20]:
          - list [ref=e21]:
            - listitem [ref=e22]:
              - link "Inicio" [ref=e23] [cursor=pointer]:
                - /url: /es
            - listitem [ref=e24]:
              - generic [ref=e25]: /
              - link "Walking Tours" [ref=e26] [cursor=pointer]:
                - /url: /es/categories/walking
            - listitem [ref=e27]:
              - generic [ref=e28]: /
              - generic [ref=e29]: Joyas Ocultas de Roma - Tour a Pie
        - generic [ref=e30]:
          - heading "Joyas Ocultas de Roma - Tour a Pie" [level=1] [ref=e31]
          - button "Guardar en lista de deseos" [ref=e32]:
            - generic [ref=e33]: ♡
        - generic [ref=e35]:
          - img [ref=e36]
          - text: Rome, Italy
        - generic [ref=e39]:
          - 'generic "Valoración: 4.5 de 5" [ref=e40]':
            - img [ref=e41]
            - generic [ref=e43]: "4.5"
            - generic [ref=e44]: "Valoración: 4.5 de 5"
          - link "(2 Reseñas)" [ref=e45] [cursor=pointer]:
            - /url: "#reviews"
      - generic [ref=e46]:
        - generic [ref=e47]:
          - button "Ver imagen 1" [ref=e48]:
            - generic [ref=e49]:
              - img [ref=e50]
              - generic [ref=e52]: Esta foto no está disponible.
          - button "Ver imagen 2" [ref=e53]:
            - generic [ref=e54]:
              - img [ref=e55]
              - generic [ref=e57]: Esta foto no está disponible.
          - button "Ver imagen 3" [ref=e58]:
            - generic [ref=e59]:
              - img [ref=e60]
              - generic [ref=e62]: Esta foto no está disponible.
          - button "Ver imagen 4" [ref=e63]:
            - generic [ref=e64]:
              - img [ref=e65]
              - generic [ref=e67]: Esta foto no está disponible.
          - button "Ver imagen 5" [ref=e68]:
            - generic [ref=e69]:
              - img [ref=e70]
              - generic [ref=e72]: Esta foto no está disponible.
        - button "Imagen anterior" [ref=e73]:
          - img [ref=e74]
        - button "Imagen siguiente" [ref=e76]:
          - img [ref=e77]
        - paragraph [ref=e79]: 1 de 5
      - navigation "Secciones del tour" [ref=e80]:
        - generic [ref=e81]:
          - link "Sobre Este Tour" [ref=e82] [cursor=pointer]:
            - /url: "#overview"
          - link "Datos del tour" [ref=e83] [cursor=pointer]:
            - /url: "#facts"
          - link "Destacados" [ref=e84] [cursor=pointer]:
            - /url: "#highlights"
          - link "Qué Incluye" [ref=e85] [cursor=pointer]:
            - /url: "#included"
          - link "Qué No Incluye" [ref=e86] [cursor=pointer]:
            - /url: "#excluded"
          - link "Punto de Encuentro" [ref=e87] [cursor=pointer]:
            - /url: "#meeting"
          - link "Política de Cancelación" [ref=e88] [cursor=pointer]:
            - /url: "#cancellation"
          - link "Reseñas" [ref=e89] [cursor=pointer]:
            - /url: "#reviews"
          - link "Más experiencias para explorar" [ref=e90] [cursor=pointer]:
            - /url: "#related"
      - generic [ref=e91]:
        - article [ref=e92]:
          - generic [ref=e93]:
            - heading "Sobre Este Tour" [level=2] [ref=e94]
            - generic [ref=e95]: Descubre los rincones secretos de Roma que la mayoría de turistas nunca ven.
          - generic [ref=e96]:
            - heading "Datos del tour" [level=2] [ref=e97]
            - generic [ref=e98]:
              - generic [ref=e99]:
                - term [ref=e100]: Duración
                - definition [ref=e101]: 3 hours
              - generic [ref=e102]:
                - term [ref=e103]: Tamaño del grupo
                - definition [ref=e104]: 1–20 viajeros
          - generic [ref=e105]:
            - heading "Destacados" [level=2] [ref=e106]
            - list [ref=e107]:
              - listitem [ref=e108]:
                - img [ref=e109]
                - text: Patiios ocultos
              - listitem [ref=e111]:
                - img [ref=e112]
                - text: Tiendas de artesanos locales
              - listitem [ref=e114]:
                - img [ref=e115]
                - text: Historias romanas antiguas
          - generic [ref=e117]:
            - heading "Qué Incluye" [level=3] [ref=e121]:
              - button "Qué Incluye" [ref=e122]:
                - text: Qué Incluye
                - img [ref=e123]
            - heading "Qué No Incluye" [level=3] [ref=e128]:
              - button "Qué No Incluye" [ref=e129]:
                - text: Qué No Incluye
                - img [ref=e130]
          - generic [ref=e132]:
            - heading "Punto de Encuentro" [level=2] [ref=e133]
            - paragraph [ref=e134]:
              - img [ref=e135]
              - text: Entrada principal del Coliseo
          - generic [ref=e138]:
            - heading "Política de Cancelación" [level=2] [ref=e139]
            - paragraph [ref=e140]: Cancelación gratuita hasta 24 horas antes.
          - region "Reseñas" [ref=e141]:
            - heading "Reseñas" [level=2] [ref=e142]
            - generic [ref=e143]:
              - heading "Reseñas" [level=3] [ref=e144]
              - generic [ref=e146]:
                - generic [ref=e147]: "4.5"
                - generic [ref=e148]:
                  - 'img "Valoración: 4.5 de 5" [ref=e149]':
                    - img [ref=e150]
                    - img [ref=e152]
                    - img [ref=e154]
                    - img [ref=e156]
                    - img [ref=e158]
                  - paragraph [ref=e160]: 2 reseñas
              - generic [ref=e161]:
                - generic [ref=e162]:
                  - generic [ref=e163]:
                    - generic [ref=e164]: Test
                    - generic [ref=e165]: Editado
                  - generic [ref=e166]:
                    - 'img "Valoración: 4 de 5" [ref=e167]':
                      - img [ref=e168]
                      - img [ref=e170]
                      - img [ref=e172]
                      - img [ref=e174]
                      - img [ref=e176]
                    - generic [ref=e178]: 28 de septiembre de 2026
                  - paragraph [ref=e179]: Updated comment value here.
                - generic [ref=e180]:
                  - generic [ref=e182]: Test
                  - generic [ref=e183]:
                    - 'img "Valoración: 5 de 5" [ref=e184]':
                      - img [ref=e185]
                      - img [ref=e187]
                      - img [ref=e189]
                      - img [ref=e191]
                      - img [ref=e193]
                    - generic [ref=e195]: 12 de septiembre de 2026
                  - paragraph [ref=e196]: A wonderful hidden-gems walk through Rome — highly recommended!
        - complementary:
          - generic [ref=e198]:
            - paragraph [ref=e199]:
              - generic [ref=e200]: 45,00 € / persona
              - generic [ref=e201]: 17/10/2026 · 1 · 45,00 €
            - generic [ref=e202]:
              - link "Continuar con la reserva" [ref=e203] [cursor=pointer]:
                - /url: /es/auth/login?returnUrl=%2Fes%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
              - button "Editar la selección de reserva" [ref=e204]
      - region "Más experiencias para explorar" [ref=e205]:
        - heading "Más experiencias para explorar" [level=2] [ref=e206]
        - generic [ref=e207]:
          - generic [ref=e208]:
            - 'link "Venice Hidden Canals Walking Tour Próxima fecha: 17 oct 2026 Venice, Italy 2.5 hours Venice Hidden Canals Walking Tour Walking Tours €55.00" [ref=e209] [cursor=pointer]':
              - /url: /es/tours/venice-walking-tour-hidden-canals
              - generic [ref=e210]:
                - img "Venice Hidden Canals Walking Tour" [ref=e211]
                - generic [ref=e212]: "Próxima fecha: 17 oct 2026"
              - generic [ref=e213]:
                - generic [ref=e214]:
                  - generic [ref=e215]: Venice, Italy
                  - generic [ref=e216]: ·
                  - generic [ref=e217]: 2.5 hours
                - heading "Venice Hidden Canals Walking Tour" [level=2] [ref=e218]
                - generic [ref=e219]:
                  - generic [ref=e220]: Walking Tours
                  - generic [ref=e221]: €55.00
            - button "Guardar en lista de deseos" [ref=e223]:
              - generic [ref=e224]: ♡
          - generic [ref=e225]:
            - 'link "Barcelona Gaudí Architecture Masterpieces Tour Próxima fecha: 17 oct 2026 Barcelona, Spain 4 hours Barcelona Gaudí Architecture Masterpieces Tour Walking Tours €65.00" [ref=e226] [cursor=pointer]':
              - /url: /es/tours/barcelona-gaudi-architecture-tour
              - generic [ref=e227]:
                - img "Barcelona Gaudí Architecture Masterpieces Tour" [ref=e228]
                - generic [ref=e229]: "Próxima fecha: 17 oct 2026"
              - generic [ref=e230]:
                - generic [ref=e231]:
                  - generic [ref=e232]: Barcelona, Spain
                  - generic [ref=e233]: ·
                  - generic [ref=e234]: 4 hours
                - heading "Barcelona Gaudí Architecture Masterpieces Tour" [level=2] [ref=e235]
                - generic [ref=e236]:
                  - generic [ref=e237]: Walking Tours
                  - generic [ref=e238]: €65.00
            - button "Guardar en lista de deseos" [ref=e240]:
              - generic [ref=e241]: ♡
          - generic [ref=e242]:
            - 'link "Montmartre Artists Quarter Walking Tour Próxima fecha: 17 oct 2026 Paris, France 2.5 hours Montmartre Artists Quarter Walking Tour Walking Tours €49.00" [ref=e243] [cursor=pointer]':
              - /url: /es/tours/paris-montmartre-artists-quarter
              - generic [ref=e244]:
                - img "Montmartre Artists Quarter Walking Tour" [ref=e245]
                - generic [ref=e246]: "Próxima fecha: 17 oct 2026"
              - generic [ref=e247]:
                - generic [ref=e248]:
                  - generic [ref=e249]: Paris, France
                  - generic [ref=e250]: ·
                  - generic [ref=e251]: 2.5 hours
                - heading "Montmartre Artists Quarter Walking Tour" [level=2] [ref=e252]
                - generic [ref=e253]:
                  - generic [ref=e254]: Walking Tours
                  - generic [ref=e255]: €49.00
            - button "Guardar en lista de deseos" [ref=e257]:
              - generic [ref=e258]: ♡
  - contentinfo [ref=e259]:
    - generic [ref=e260]:
      - generic [ref=e261]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e264]
        - generic [ref=e265]:
          - paragraph [ref=e266]: Explorar
          - list [ref=e267]:
            - listitem [ref=e268]:
              - link "Buscar tours" [ref=e269] [cursor=pointer]:
                - /url: /es/search
            - listitem [ref=e270]:
              - link "Categorías" [ref=e271] [cursor=pointer]:
                - /url: /es/categories
            - listitem [ref=e272]:
              - link "Destinos" [ref=e273] [cursor=pointer]:
                - /url: /es/destinations
        - generic [ref=e274]:
          - paragraph [ref=e275]: Empresa
          - list [ref=e276]:
            - listitem [ref=e277]:
              - link "Política de privacidad" [ref=e278] [cursor=pointer]:
                - /url: /es/privacy
            - listitem [ref=e279]:
              - link "Términos del servicio" [ref=e280] [cursor=pointer]:
                - /url: /es/terms
      - generic [ref=e281]:
        - generic [ref=e282]: © 2026 Bookly. Todos los derechos reservados.
        - combobox "Cambiar idioma" [ref=e284]:
          - option "English"
          - option "Español" [selected]
          - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e285]:
      - generic [ref=e286]: Usamos cookies para mejorar tu experiencia de navegación y analizar nuestro tráfico. Al hacer clic en "Aceptar", aceptas nuestro uso de cookies.
      - generic [ref=e287]:
        - button "Rechazar" [ref=e288]
        - button "Aceptar" [ref=e289]
  - alert [ref=e290]
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
> 79  |     expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
      |                                                                   ^ Error: expect(received).toEqual(expected) // deep equality
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
```