# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> ES/IT SSR pages replace disclosed English fallback with ready localized content
- Location: tests\e2e\tour-detail.spec.ts:77:7

# Error details

```
Error: expect(locator).toContainText(expected) failed

Locator: locator('#itinerary')
Expected substring: "Llegada española"
Error: strict mode violation: locator('#itinerary') resolved to 2 elements:
    1) <section id="itinerary" class="scroll-mt-20">…</section> aka locator('#main-content').getByText('ItinerarioDía 1: Llegada espa')
    2) <section id="itinerary" class="scroll-mt-20">…</section> aka getByText('ItinerarioDía 1: Llegada espa').nth(1)

Call log:
  - Expect "toContainText" with timeout 5000ms
  - waiting for locator('#itinerary')

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
              - generic [ref=e34]: Título español listo
        - heading "Título español listo" [level=1] [ref=e35]
        - generic [ref=e37]:
          - img [ref=e38]
          - text: Rome, Italy
      - status [ref=e42]: No hay fotos disponibles para este tour.
      - navigation "Secciones del tour" [ref=e43]:
        - generic [ref=e44]:
          - link "Sobre Este Tour" [ref=e45] [cursor=pointer]:
            - /url: "#overview"
          - link "Datos del tour" [ref=e46] [cursor=pointer]:
            - /url: "#facts"
          - link "Destacados" [ref=e47] [cursor=pointer]:
            - /url: "#highlights"
          - link "Itinerario" [ref=e48] [cursor=pointer]:
            - /url: "#itinerary"
          - link "Qué Incluye" [ref=e49] [cursor=pointer]:
            - /url: "#included"
          - link "Qué No Incluye" [ref=e50] [cursor=pointer]:
            - /url: "#excluded"
          - link "Punto de Encuentro" [ref=e51] [cursor=pointer]:
            - /url: "#meeting"
          - link "Política de Cancelación" [ref=e52] [cursor=pointer]:
            - /url: "#cancellation"
      - generic [ref=e53]:
        - article [ref=e54]:
          - generic [ref=e55]:
            - heading "Sobre Este Tour" [level=2] [ref=e56]
            - generic [ref=e57]: Localized content for es
          - generic [ref=e58]:
            - heading "Datos del tour" [level=2] [ref=e59]
            - generic [ref=e60]:
              - generic [ref=e61]:
                - term [ref=e62]: Duración
                - definition [ref=e63]: 3 hours
              - generic [ref=e64]:
                - term [ref=e65]: Tamaño del grupo
                - definition [ref=e66]: 1–20 viajeros
            - paragraph [ref=e67]:
              - generic [ref=e68]: "Guía en vivo:"
              - text: Alemán, Inglés, Español
          - generic [ref=e69]:
            - heading "Destacados" [level=2] [ref=e70]
            - list [ref=e71]:
              - listitem [ref=e72]:
                - img [ref=e73]
                - text: Hidden courtyards
              - listitem [ref=e75]:
                - img [ref=e76]
                - text: Local artisan shops
              - listitem [ref=e78]:
                - img [ref=e79]
                - text: Ancient Roman stories
              - listitem [ref=e81]:
                - img [ref=e82]
                - text: Off the beaten path
          - generic [ref=e84]:
            - heading "Itinerario" [level=2] [ref=e85]
            - list [ref=e86]:
              - listitem [ref=e87]:
                - 'heading "Día 1: Llegada española" [level=3] [ref=e88]'
                - list [ref=e89]:
                  - listitem [ref=e90]: Llegada española
          - generic [ref=e91]:
            - heading "Qué Incluye" [level=3] [ref=e95]:
              - button "Qué Incluye" [ref=e96]:
                - text: Qué Incluye
                - img [ref=e97]
            - heading "Qué No Incluye" [level=3] [ref=e102]:
              - button "Qué No Incluye" [ref=e103]:
                - text: Qué No Incluye
                - img [ref=e104]
          - generic [ref=e106]:
            - heading "Punto de Encuentro" [level=2] [ref=e107]
            - paragraph [ref=e108]:
              - img [ref=e109]
              - text: Colosseum main entrance
          - generic [ref=e112]:
            - heading "Política de Cancelación" [level=2] [ref=e113]
            - paragraph [ref=e114]: Free cancellation up to 24 hours before the tour.
        - complementary [ref=e115]:
          - generic [ref=e117]:
            - generic [ref=e118]:
              - generic [ref=e119]:
                - text: 45,00 €
                - generic [ref=e120]: / persona
              - button "Guardar en lista de deseos" [ref=e121]:
                - generic [ref=e122]: ♡
            - status [ref=e123]:
              - paragraph [ref=e124]: No Disponible
              - paragraph [ref=e125]: No hay fechas próximas disponibles para este tour. Vuelve pronto.
              - button "Reservar Ahora" [disabled] [ref=e126]
  - contentinfo [ref=e127]:
    - generic [ref=e128]:
      - generic [ref=e129]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e132]
        - generic [ref=e133]:
          - paragraph [ref=e134]: Explorar
          - list [ref=e135]:
            - listitem [ref=e136]:
              - link "Buscar tours" [ref=e137] [cursor=pointer]:
                - /url: /es/search
            - listitem [ref=e138]:
              - link "Categorías" [ref=e139] [cursor=pointer]:
                - /url: /es/categories
            - listitem [ref=e140]:
              - link "Destinos" [ref=e141] [cursor=pointer]:
                - /url: /es/destinations
        - generic [ref=e142]:
          - paragraph [ref=e143]: Empresa
          - list [ref=e144]:
            - listitem [ref=e145]:
              - link "Política de privacidad" [ref=e146] [cursor=pointer]:
                - /url: /es/privacy
            - listitem [ref=e147]:
              - link "Términos del servicio" [ref=e148] [cursor=pointer]:
                - /url: /es/terms
      - generic [ref=e149]:
        - generic [ref=e150]: © 2026 Bookly. Todos los derechos reservados.
        - combobox "Cambiar idioma" [ref=e152]:
          - option "English"
          - option "Español" [selected]
          - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e153]:
      - generic [ref=e154]: Usamos cookies para mejorar tu experiencia de navegación y analizar nuestro tráfico. Al hacer clic en "Aceptar", aceptas nuestro uso de cookies.
      - generic [ref=e155]:
        - button "Rechazar" [ref=e156]
        - button "Aceptar" [ref=e157]
  - alert [ref=e158]
```

# Test source

```ts
  57  |         { day: 1, title: 'Legacy arrival', description: null, stops: [] },
  58  |         { day: 2, title: 'Legacy walking route', description: null, stops: [] },
  59  |       ] };
  60  |       await page.reload();
  61  |       await expect(page.locator('#itinerary')).toContainText('Legacy arrival');
  62  |       await expect(page.locator('#itinerary')).toContainText('Legacy walking route');
  63  |       const adopted = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
  64  |       expect(adopted.itinerary.itemListElement.map((day: { position: number; identifier: number; name: string }) =>
  65  |         [day.position, day.identifier, day.name])).toEqual([[1, 1, 'Legacy arrival'], [2, 2, 'Legacy walking route']]);
  66  |       expect(adopted.itinerary['@context'].name['@language']).toBe('en');
  67  |       expect(adopted.image).toBeUndefined();
  68  |       expect(adopted.aggregateRating).toBeUndefined();
  69  |       expect(adopted.tripOrigin).toBeUndefined();
  70  |       await expect(page.locator('main [class*="aspect-[16/10]"] img')).toHaveCount(0);
  71  |       expect(adopted.offers.price).toBe((fixture.pricing.base_price.amount / 100).toFixed(2));
  72  |       expect(adopted.offers.priceCurrency).toBe(fixture.pricing.base_price.currency);
  73  |     } finally {
  74  |       await harness.stop();
  75  |     }
  76  |   });
  77  |   test('ES/IT SSR pages replace disclosed English fallback with ready localized content', async ({ page, request }) => {
  78  |     test.setTimeout(120000);
  79  |     const slug = 'hidden-gems-rome-walking-tour';
  80  |     const fixtureResponse = await request.get(`/api/public/tours/${slug}?locale=en`);
  81  |     expect(fixtureResponse.ok()).toBeTruthy();
  82  |     const fixture = (await fixtureResponse.json()).data as TourDetail;
  83  |     let current = structuredClone(fixture);
  84  |     const harness = await startSsrHarness(slug, () => current);
  85  |     const { mockPort, nextPort } = harness;
  86  | 
  87  |     try {
  88  |       for (const scenario of [
  89  |         { locale: 'es' as const, title: 'Título español listo', day: 'Llegada española', notice: 'El contenido se muestra en inglés', itineraryNotice: 'El itinerario se muestra en inglés', guide: 'Guía en vivo' },
  90  |         { locale: 'it' as const, title: 'Titolo italiano pronto', day: 'Arrivo italiano', notice: 'Il contenuto è mostrato in inglese', itineraryNotice: "L'itinerario è mostrato in inglese", guide: 'Guida dal vivo' },
  91  |       ]) {
  92  |         const source = structuredClone(fixture);
  93  |         source.title = 'Canonical English source';
  94  |         source.description = 'The current English source remains visible until translation is ready.';
  95  |         source.content_locale = 'en';
  96  |         source.itinerary_locale = 'en';
  97  |         source.itinerary = [{ day: 1, title: 'English arrival', stops: [{ title: 'Meet the guide' }] }];
  98  |         source.guide_languages = ['de', 'en', 'es'];
  99  |         source.languages = source.guide_languages;
  100 |         source.translation_status = 'pending';
  101 |         source.translation_warning = 'partial_translation';
  102 |         source.images = [];
  103 |         source.operator = null;
  104 |         source.related_tours = [];
  105 |         source.reviews = { average_rating: 0, count: 0, distribution: {} };
  106 |         source.rating = { average: 0, count: 0 };
  107 |         source.availability = { ...source.availability, available_dates: [], next_available_date: null, is_unavailable: true };
  108 |         source.seo = { ...source.seo, meta_title: `${source.title} | Bookly`, meta_description: source.description };
  109 |         current = source;
  110 | 
  111 |         // Boundary response the isolated Next server actually consumes.
  112 |         const servedFallback = await readServedDetail(mockPort, slug, scenario.locale);
  113 |         expect(servedFallback.content_locale).toBe('en');
  114 |         expect(servedFallback.itinerary_locale).toBe('en');
  115 |         expect(servedFallback.translation_status).toBe('pending');
  116 |         expect(servedFallback.translation_warning).toBe('partial_translation');
  117 |         expect(servedFallback.guide_languages).toEqual(['de', 'en', 'es']);
  118 | 
  119 |         const url = `http://127.0.0.1:${nextPort}/${scenario.locale}/tours/${slug}`;
  120 |         await page.goto(url);
  121 |         const fallbackHeading = page.getByRole('heading', { level: 1 });
  122 |         await expect(fallbackHeading).toHaveText(source.title);
  123 |         // Spec 019 US2: full English fallback discloses general content and
  124 |         // the nonempty itinerary independently; the URL stays stable. Actual
  125 |         // fallback text declares lang="en" on the requested-locale page.
  126 |         expect(page.url()).toBe(url);
  127 |         await expect(page.getByText(scenario.notice)).toBeVisible();
  128 |         await expect(page.getByText(scenario.itineraryNotice)).toBeVisible();
  129 |         await expect(fallbackHeading).toHaveAttribute('lang', 'en');
  130 |         await expect(page.locator('#itinerary')).toContainText('English arrival');
  131 |         await expect(page.locator('#itinerary h3 span').first()).toHaveAttribute('lang', 'en');
  132 |         await expect(page.getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
  133 | 
  134 |         current = { ...source,
  135 |           title: scenario.title,
  136 |           description: `Localized content for ${scenario.locale}`,
  137 |           content_locale: scenario.locale,
  138 |           itinerary_locale: scenario.locale,
  139 |           itinerary: [{ day: 1, title: scenario.day, stops: [{ title: scenario.day }] }],
  140 |           translation_status: 'ready',
  141 |           translation_warning: undefined,
  142 |           seo: { ...source.seo, meta_title: `${scenario.title} | Bookly`, meta_description: `Localized content for ${scenario.locale}` },
  143 |         };
  144 |         const servedReady = await readServedDetail(mockPort, slug, scenario.locale);
  145 |         expect(servedReady.content_locale).toBe(scenario.locale);
  146 |         expect(servedReady.itinerary_locale).toBe(scenario.locale);
  147 |         expect(servedReady.translation_status).toBe('ready');
  148 |         expect(servedReady.translation_warning).toBeUndefined();
  149 | 
  150 |         await page.reload();
  151 |         expect(page.url()).toBe(url);
  152 |         const readyHeading = page.getByRole('heading', { level: 1 });
  153 |         await expect(readyHeading).toHaveText(scenario.title);
  154 |         await expect(page.getByText(scenario.notice)).toHaveCount(0);
  155 |         await expect(page.getByText(scenario.itineraryNotice)).toHaveCount(0);
  156 |         await expect(readyHeading).toHaveAttribute('lang', scenario.locale);
> 157 |         await expect(page.locator('#itinerary')).toContainText(scenario.day);
      |                                                  ^ Error: expect(locator).toContainText(expected) failed
  158 |         await expect(page.locator('#itinerary')).not.toContainText('English arrival');
  159 |         await expect(page.locator('#itinerary h3 span').first()).toHaveAttribute('lang', scenario.locale);
  160 |         await expect(page.getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
  161 |         const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
  162 |         expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
  163 |         expect(schema['@context'].name['@language']).toBe(scenario.locale);
  164 |         expect(schema.mainEntityOfPage.inLanguage).toBe(scenario.locale);
  165 |         expect(schema.inLanguage).toBeUndefined();
  166 |       }
  167 |     } finally {
  168 |       await harness.stop();
  169 |     }
  170 |   });
  171 |   // Root correction R5: independent general/itinerary transition in both
  172 |   // ES and IT, with served-response assertions, actual lang attributes,
  173 |   // stable URLs, localized controls and stable guide codes.
  174 |   test('ES/IT SSR keeps current general content while the itinerary falls back, then shows ready on reload', async ({ page, request }) => {
  175 |     test.setTimeout(180000);
  176 |     const slug = 'hidden-gems-rome-walking-tour';
  177 |     const fixtureResponse = await request.get(`/api/public/tours/${slug}?locale=en`);
  178 |     expect(fixtureResponse.ok()).toBeTruthy();
  179 |     const fixture = (await fixtureResponse.json()).data as TourDetail;
  180 |     let current = structuredClone(fixture);
  181 |     const harness = await startSsrHarness(slug, () => current);
  182 |     const { mockPort, nextPort } = harness;
  183 | 
  184 |     try {
  185 |       for (const scenario of [
  186 |         { locale: 'es' as const, title: 'Título español actual', description: 'Descripción española actual.', day: 'Llegada española', contentNotice: 'El contenido se muestra en inglés', itineraryNotice: 'El itinerario se muestra en inglés', guide: 'Guía en vivo' },
  187 |         { locale: 'it' as const, title: 'Titolo italiano attuale', description: 'Descrizione italiana attuale.', day: 'Arrivo italiano', contentNotice: 'Il contenuto è mostrato in inglese', itineraryNotice: "L'itinerario è mostrato in inglese", guide: 'Guida dal vivo' },
  188 |       ]) {
  189 |         // Phase 1: current requested general content, unavailable requested
  190 |         // itinerary -> independent English itinerary fallback with its own
  191 |         // notice; general content stays requested with no content notice.
  192 |         const source = structuredClone(fixture);
  193 |         source.title = scenario.title;
  194 |         source.description = scenario.description;
  195 |         source.content_locale = scenario.locale;
  196 |         source.itinerary_locale = 'en';
  197 |         source.itinerary = [{ day: 1, title: 'English arrival', stops: [{ title: 'Meet the guide' }] }];
  198 |         source.guide_languages = ['de', 'en', 'es'];
  199 |         source.languages = source.guide_languages;
  200 |         source.translation_status = 'ready';
  201 |         source.translation_warning = 'partial_translation';
  202 |         source.images = [];
  203 |         source.operator = null;
  204 |         source.related_tours = [];
  205 |         source.reviews = { average_rating: 0, count: 0, distribution: {} };
  206 |         source.rating = { average: 0, count: 0 };
  207 |         source.availability = { ...source.availability, available_dates: [], next_available_date: null, is_unavailable: true };
  208 |         source.seo = { ...source.seo, meta_title: `${source.title} | Bookly`, meta_description: source.description };
  209 |         current = source;
  210 | 
  211 |         const servedFallback = await readServedDetail(mockPort, slug, scenario.locale);
  212 |         expect(servedFallback.content_locale).toBe(scenario.locale);
  213 |         expect(servedFallback.itinerary_locale).toBe('en');
  214 |         expect(servedFallback.translation_status).toBe('ready');
  215 |         expect(servedFallback.translation_warning).toBe('partial_translation');
  216 |         expect(servedFallback.guide_languages).toEqual(['de', 'en', 'es']);
  217 | 
  218 |         const url = `http://127.0.0.1:${nextPort}/${scenario.locale}/tours/${slug}`;
  219 |         await page.goto(url);
  220 |         const fallbackHeading = page.getByRole('heading', { level: 1 });
  221 |         await expect(fallbackHeading).toHaveText(source.title);
  222 |         expect(page.url()).toBe(url);
  223 |         await expect(page.getByText(scenario.itineraryNotice)).toBeVisible();
  224 |         await expect(page.getByText(scenario.contentNotice)).toHaveCount(0);
  225 |         await expect(fallbackHeading).toHaveAttribute('lang', scenario.locale);
  226 |         await expect(page.locator('#itinerary')).toContainText('English arrival');
  227 |         await expect(page.locator('#itinerary h3 span').first()).toHaveAttribute('lang', 'en');
  228 |         await expect(page.getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
  229 | 
  230 |         // Phase 2: the requested itinerary becomes current. The same URL
  231 |         // reload (fresh SSR read) replaces the fallback without re-entry or
  232 |         // a route change; spoken guide codes stay stable.
  233 |         current = { ...source,
  234 |           itinerary_locale: scenario.locale,
  235 |           itinerary: [{ day: 1, title: scenario.day, stops: [{ title: scenario.day }] }],
  236 |           translation_warning: undefined,
  237 |           seo: { ...source.seo },
  238 |         };
  239 |         const servedReady = await readServedDetail(mockPort, slug, scenario.locale);
  240 |         expect(servedReady.content_locale).toBe(scenario.locale);
  241 |         expect(servedReady.itinerary_locale).toBe(scenario.locale);
  242 |         expect(servedReady.translation_warning).toBeUndefined();
  243 | 
  244 |         await page.reload();
  245 |         expect(page.url()).toBe(url);
  246 |         const readyHeading = page.getByRole('heading', { level: 1 });
  247 |         await expect(readyHeading).toHaveText(source.title);
  248 |         await expect(readyHeading).toHaveAttribute('lang', scenario.locale);
  249 |         await expect(page.locator('#itinerary')).toContainText(scenario.day);
  250 |         await expect(page.locator('#itinerary')).not.toContainText('English arrival');
  251 |         await expect(page.locator('#itinerary h3 span').first()).toHaveAttribute('lang', scenario.locale);
  252 |         await expect(page.getByText(scenario.itineraryNotice)).toHaveCount(0);
  253 |         await expect(page.getByText(scenario.contentNotice)).toHaveCount(0);
  254 |         await expect(page.getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
  255 |         const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
  256 |         expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
  257 |         expect(schema['@context'].name['@language']).toBe(scenario.locale);
```