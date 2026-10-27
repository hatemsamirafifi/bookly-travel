# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> ES/IT SSR pages replace disclosed English fallback with ready localized content
- Location: tests\e2e\tour-detail.spec.ts:81:7

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('Il contenuto è mostrato in inglese')
Expected: visible
Error: strict mode violation: getByText('Il contenuto è mostrato in inglese') resolved to 2 elements:
    1) <p class="inline-block rounded border border-warning/30 bg-warning/10 px-2 py-0.5 text-xs font-medium text-warning">Il contenuto è mostrato in inglese</p> aka locator('#main-content').getByText('Il contenuto è mostrato in')
    2) <p class="inline-block rounded border border-warning/30 bg-warning/10 px-2 py-0.5 text-xs font-medium text-warning">Il contenuto è mostrato in inglese</p> aka getByText('Il contenuto è mostrato in').nth(1)

Call log:
  - Expect "toBeVisible" with timeout 5000ms
  - waiting for getByText('Il contenuto è mostrato in inglese')

```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - banner [ref=e2]:
    - link "Vai al contenuto principale" [ref=e3] [cursor=pointer]:
      - /url: "#main-content"
    - generic [ref=e4]:
      - link "Bookly" [ref=e5] [cursor=pointer]:
        - /url: /it
      - navigation "Navigazione principale" [ref=e6]:
        - link "Home" [ref=e7] [cursor=pointer]:
          - /url: /it
        - link "Cerca tour" [ref=e8] [cursor=pointer]:
          - /url: /it/search
        - link "Categorie" [ref=e9] [cursor=pointer]:
          - /url: /it/categories
        - link "Destinazioni" [ref=e10] [cursor=pointer]:
          - /url: /it/destinations
      - generic [ref=e11]:
        - button "Cerca tour" [ref=e12]:
          - img [ref=e13]
        - combobox "Cambia lingua" [ref=e18]:
          - option "English"
          - option "Español"
          - option "Italiano" [selected]
        - link "Accedi" [ref=e19] [cursor=pointer]:
          - /url: /it/auth/login
          - generic [ref=e20]: Accedi
        - link "Registrati" [ref=e21] [cursor=pointer]:
          - /url: /it/auth/register
  - main [ref=e22]:
    - generic [ref=e23]:
      - generic [ref=e24]:
        - navigation "Percorso di navigazione" [ref=e25]:
          - list [ref=e26]:
            - listitem [ref=e27]:
              - link "Home" [ref=e28] [cursor=pointer]:
                - /url: /it
            - listitem [ref=e29]:
              - generic [ref=e30]: /
              - link "Walking Tours" [ref=e31] [cursor=pointer]:
                - /url: /it/categories/walking
            - listitem [ref=e32]:
              - generic [ref=e33]: /
              - generic [ref=e34]: Canonical English source
        - heading "Canonical English source" [level=1] [ref=e35]
        - generic [ref=e37]:
          - img [ref=e38]
          - text: Rome, Italy
      - generic [ref=e41]:
        - paragraph [ref=e42]: Il contenuto è mostrato in inglese
        - paragraph [ref=e43]: L'itinerario è mostrato in inglese
      - status [ref=e45]: Nessuna foto disponibile per questo tour.
      - navigation "Sezioni del tour" [ref=e46]:
        - generic [ref=e47]:
          - link "Informazioni sul Tour" [ref=e48] [cursor=pointer]:
            - /url: "#overview"
          - link "Dettagli del tour" [ref=e49] [cursor=pointer]:
            - /url: "#facts"
          - link "Punti Salienti" [ref=e50] [cursor=pointer]:
            - /url: "#highlights"
          - link "Itinerario" [ref=e51] [cursor=pointer]:
            - /url: "#itinerary"
          - link "Cosa è Incluso" [ref=e52] [cursor=pointer]:
            - /url: "#included"
          - link "Cosa è Escluso" [ref=e53] [cursor=pointer]:
            - /url: "#excluded"
          - link "Punto di Incontro" [ref=e54] [cursor=pointer]:
            - /url: "#meeting"
          - link "Politica di Cancellazione" [ref=e55] [cursor=pointer]:
            - /url: "#cancellation"
      - generic [ref=e56]:
        - article [ref=e57]:
          - generic [ref=e58]:
            - heading "Informazioni sul Tour" [level=2] [ref=e59]
            - generic [ref=e60]: The current English source remains visible until translation is ready.
          - generic [ref=e61]:
            - heading "Dettagli del tour" [level=2] [ref=e62]
            - generic [ref=e63]:
              - generic [ref=e64]:
                - term [ref=e65]: Durata
                - definition [ref=e66]: 3 hours
              - generic [ref=e67]:
                - term [ref=e68]: Dimensione del gruppo
                - definition [ref=e69]: 1–20 viaggiatori
            - paragraph [ref=e70]:
              - generic [ref=e71]: "Guida dal vivo:"
              - text: Tedesco, Inglese, Spagnolo
          - generic [ref=e72]:
            - heading "Punti Salienti" [level=2] [ref=e73]
            - list [ref=e74]:
              - listitem [ref=e75]:
                - img [ref=e76]
                - text: Hidden courtyards
              - listitem [ref=e78]:
                - img [ref=e79]
                - text: Local artisan shops
              - listitem [ref=e81]:
                - img [ref=e82]
                - text: Ancient Roman stories
              - listitem [ref=e84]:
                - img [ref=e85]
                - text: Off the beaten path
          - generic [ref=e87]:
            - heading "Itinerario" [level=2] [ref=e88]
            - list [ref=e89]:
              - listitem [ref=e90]:
                - 'heading "Giorno 1: English arrival" [level=3] [ref=e91]'
                - list [ref=e92]:
                  - listitem [ref=e93]: Meet the guide
          - generic [ref=e94]:
            - heading "Cosa è Incluso" [level=3] [ref=e98]:
              - button "Cosa è Incluso" [ref=e99]:
                - text: Cosa è Incluso
                - img [ref=e100]
            - heading "Cosa è Escluso" [level=3] [ref=e105]:
              - button "Cosa è Escluso" [ref=e106]:
                - text: Cosa è Escluso
                - img [ref=e107]
          - generic [ref=e109]:
            - heading "Punto di Incontro" [level=2] [ref=e110]
            - paragraph [ref=e111]:
              - img [ref=e112]
              - text: Colosseum main entrance
          - generic [ref=e115]:
            - heading "Politica di Cancellazione" [level=2] [ref=e116]
            - paragraph [ref=e117]: Free cancellation up to 24 hours before the tour.
        - complementary [ref=e118]:
          - generic [ref=e120]:
            - generic [ref=e121]:
              - generic [ref=e122]:
                - text: 45,00 €
                - generic [ref=e123]: / persona
              - button "Salva nella lista desideri" [ref=e124]:
                - generic [ref=e125]: ♡
            - status [ref=e126]:
              - paragraph [ref=e127]: Non Disponibile
              - paragraph [ref=e128]: Nessuna data disponibile per questo tour. Torna a controllare presto.
              - button "Prenota Ora" [disabled] [ref=e129]
  - contentinfo [ref=e130]:
    - generic [ref=e131]:
      - generic [ref=e132]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e135]
        - generic [ref=e136]:
          - paragraph [ref=e137]: Esplora
          - list [ref=e138]:
            - listitem [ref=e139]:
              - link "Cerca tour" [ref=e140] [cursor=pointer]:
                - /url: /it/search
            - listitem [ref=e141]:
              - link "Categorie" [ref=e142] [cursor=pointer]:
                - /url: /it/categories
            - listitem [ref=e143]:
              - link "Destinazioni" [ref=e144] [cursor=pointer]:
                - /url: /it/destinations
        - generic [ref=e145]:
          - paragraph [ref=e146]: Azienda
          - list [ref=e147]:
            - listitem [ref=e148]:
              - link "Informativa sulla privacy" [ref=e149] [cursor=pointer]:
                - /url: /it/privacy
            - listitem [ref=e150]:
              - link "Termini di servizio" [ref=e151] [cursor=pointer]:
                - /url: /it/terms
      - generic [ref=e152]:
        - generic [ref=e153]: © 2026 Bookly. Tutti i diritti riservati.
        - combobox "Cambia lingua" [ref=e155]:
          - option "English"
          - option "Español"
          - option "Italiano" [selected]
  - complementary "Cookie consent":
    - generic [ref=e156]:
      - generic [ref=e157]: Utilizziamo i cookie per migliorare la tua esperienza di navigazione e analizzare il nostro traffico. Facendo clic su "Accetta", acconsenti al nostro uso dei cookie.
      - generic [ref=e158]:
        - button "Rifiuta" [ref=e159]
        - button "Accetta" [ref=e160]
  - alert [ref=e161]
```

# Test source

```ts
  31  |     test.setTimeout(120000);
  32  |     const slug = 'hidden-gems-rome-walking-tour';
  33  |     const baseline = await request.get(`/api/public/tours/${slug}?locale=en`);
  34  |     expect(baseline.status()).toBe(200);
  35  |     // Controlled response fixtures exercise the SSR consumer; real command
  36  |     // adoption and retained legacy storage are verified separately on PostgreSQL.
  37  |     const fixture = (await baseline.json()).data as TourDetail;
  38  |     let current: TourDetail = {
  39  |       ...structuredClone(fixture),
  40  |       content_locale: 'en', itinerary_locale: 'en', translation_status: 'source',
  41  |       translation_warning: undefined, difficulty_level: null,
  42  |       itinerary: [], images: [], guide_languages: [], languages: [],
  43  |       meeting_point: '', operator: null, related_tours: [],
  44  |       reviews: { average_rating: 0, count: 0, distribution: {} },
  45  |       rating: { average: 0, count: 0 },
  46  |     };
  47  |     const harness = await startSsrHarness(slug, () => current);
  48  |     try {
  49  |       const url = `http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`;
  50  |       await page.goto(url);
  51  |       await expect(page.getByRole('heading', { level: 1 })).toHaveText(current.title);
  52  |       await expect(page.locator('#itinerary')).toHaveCount(0);
  53  |       await expect(page.getByRole('button', { name: /Open image/i })).toHaveCount(0);
  54  |       await expect(page.locator('main [class*="aspect-[16/10]"] img')).toHaveCount(0);
  55  |       const empty = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
  56  |       expect(empty.image).toBeUndefined();
  57  |       expect(empty.aggregateRating).toBeUndefined();
  58  |       expect(empty.tripOrigin).toBeUndefined();
  59  |       expect(empty.itinerary).toBeUndefined();
  60  |       current = { ...current, itinerary: [
  61  |         { day: 1, title: 'Legacy arrival', description: null, stops: [] },
  62  |         { day: 2, title: 'Legacy walking route', description: null, stops: [] },
  63  |       ] };
  64  |       await page.reload();
  65  |       await expect(page.locator('#itinerary')).toContainText('Legacy arrival');
  66  |       await expect(page.locator('#itinerary')).toContainText('Legacy walking route');
  67  |       const adopted = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
  68  |       expect(adopted.itinerary.itemListElement.map((day: { position: number; identifier: number; name: string }) =>
  69  |         [day.position, day.identifier, day.name])).toEqual([[1, 1, 'Legacy arrival'], [2, 2, 'Legacy walking route']]);
  70  |       expect(adopted.itinerary['@context'].name['@language']).toBe('en');
  71  |       expect(adopted.image).toBeUndefined();
  72  |       expect(adopted.aggregateRating).toBeUndefined();
  73  |       expect(adopted.tripOrigin).toBeUndefined();
  74  |       await expect(page.locator('main [class*="aspect-[16/10]"] img')).toHaveCount(0);
  75  |       expect(adopted.offers.price).toBe((fixture.pricing.base_price.amount / 100).toFixed(2));
  76  |       expect(adopted.offers.priceCurrency).toBe(fixture.pricing.base_price.currency);
  77  |     } finally {
  78  |       await harness.stop();
  79  |     }
  80  |   });
  81  |   test('ES/IT SSR pages replace disclosed English fallback with ready localized content', async ({ page, request }) => {
  82  |     test.setTimeout(120000);
  83  |     const slug = 'hidden-gems-rome-walking-tour';
  84  |     const fixtureResponse = await request.get(`/api/public/tours/${slug}?locale=en`);
  85  |     expect(fixtureResponse.ok()).toBeTruthy();
  86  |     const fixture = (await fixtureResponse.json()).data as TourDetail;
  87  |     let current = structuredClone(fixture);
  88  |     const harness = await startSsrHarness(slug, () => current);
  89  |     const { mockPort, nextPort } = harness;
  90  | 
  91  |     try {
  92  |       for (const scenario of [
  93  |         { locale: 'es' as const, title: 'Título español listo', day: 'Llegada española', notice: 'El contenido se muestra en inglés', itineraryNotice: 'El itinerario se muestra en inglés', guide: 'Guía en vivo' },
  94  |         { locale: 'it' as const, title: 'Titolo italiano pronto', day: 'Arrivo italiano', notice: 'Il contenuto è mostrato in inglese', itineraryNotice: "L'itinerario è mostrato in inglese", guide: 'Guida dal vivo' },
  95  |       ]) {
  96  |         const source = structuredClone(fixture);
  97  |         source.title = 'Canonical English source';
  98  |         source.description = 'The current English source remains visible until translation is ready.';
  99  |         source.content_locale = 'en';
  100 |         source.itinerary_locale = 'en';
  101 |         source.itinerary = [{ day: 1, title: 'English arrival', stops: [{ title: 'Meet the guide' }] }];
  102 |         source.guide_languages = ['de', 'en', 'es'];
  103 |         source.languages = source.guide_languages;
  104 |         source.translation_status = 'pending';
  105 |         source.translation_warning = 'partial_translation';
  106 |         source.images = [];
  107 |         source.operator = null;
  108 |         source.related_tours = [];
  109 |         source.reviews = { average_rating: 0, count: 0, distribution: {} };
  110 |         source.rating = { average: 0, count: 0 };
  111 |         source.availability = { ...source.availability, available_dates: [], next_available_date: null, is_unavailable: true };
  112 |         source.seo = { ...source.seo, meta_title: `${source.title} | Bookly`, meta_description: source.description };
  113 |         current = source;
  114 | 
  115 |         // Boundary response the isolated Next server actually consumes.
  116 |         const servedFallback = await readServedDetail(mockPort, slug, scenario.locale);
  117 |         expect(servedFallback.content_locale).toBe('en');
  118 |         expect(servedFallback.itinerary_locale).toBe('en');
  119 |         expect(servedFallback.translation_status).toBe('pending');
  120 |         expect(servedFallback.translation_warning).toBe('partial_translation');
  121 |         expect(servedFallback.guide_languages).toEqual(['de', 'en', 'es']);
  122 | 
  123 |         const url = `http://127.0.0.1:${nextPort}/${scenario.locale}/tours/${slug}`;
  124 |         await page.goto(url);
  125 |         const fallbackHeading = page.getByRole('heading', { level: 1 });
  126 |         await expect(fallbackHeading).toHaveText(source.title);
  127 |         // Spec 019 US2: full English fallback discloses general content and
  128 |         // the nonempty itinerary independently; the URL stays stable. Actual
  129 |         // fallback text declares lang="en" on the requested-locale page.
  130 |         expect(page.url()).toBe(url);
> 131 |         await expect(page.getByText(scenario.notice)).toBeVisible();
      |                                                       ^ Error: expect(locator).toBeVisible() failed
  132 |         await expect(page.getByText(scenario.itineraryNotice)).toBeVisible();
  133 |         await expect(fallbackHeading).toHaveAttribute('lang', 'en');
  134 |         await expect(page.locator('#itinerary')).toContainText('English arrival');
  135 |         await expect(page.locator('#itinerary h3 span').first()).toHaveAttribute('lang', 'en');
  136 |         await expect(page.getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
  137 | 
  138 |         current = { ...source,
  139 |           title: scenario.title,
  140 |           description: `Localized content for ${scenario.locale}`,
  141 |           content_locale: scenario.locale,
  142 |           itinerary_locale: scenario.locale,
  143 |           itinerary: [{ day: 1, title: scenario.day, stops: [{ title: scenario.day }] }],
  144 |           translation_status: 'ready',
  145 |           translation_warning: undefined,
  146 |           seo: { ...source.seo, meta_title: `${scenario.title} | Bookly`, meta_description: `Localized content for ${scenario.locale}` },
  147 |         };
  148 |         const servedReady = await readServedDetail(mockPort, slug, scenario.locale);
  149 |         expect(servedReady.content_locale).toBe(scenario.locale);
  150 |         expect(servedReady.itinerary_locale).toBe(scenario.locale);
  151 |         expect(servedReady.translation_status).toBe('ready');
  152 |         expect(servedReady.translation_warning).toBeUndefined();
  153 | 
  154 |         await page.reload();
  155 |         expect(page.url()).toBe(url);
  156 |         const readyHeading = page.getByRole('heading', { level: 1 });
  157 |         await expect(readyHeading).toHaveText(scenario.title);
  158 |         await expect(page.getByText(scenario.notice)).toHaveCount(0);
  159 |         await expect(page.getByText(scenario.itineraryNotice)).toHaveCount(0);
  160 |         await expect(readyHeading).toHaveAttribute('lang', scenario.locale);
  161 |         await expect(page.locator('#itinerary')).toContainText(scenario.day);
  162 |         await expect(page.locator('#itinerary')).not.toContainText('English arrival');
  163 |         await expect(page.locator('#itinerary h3 span').first()).toHaveAttribute('lang', scenario.locale);
  164 |         await expect(page.getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
  165 |         const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
  166 |         expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
  167 |         expect(schema['@context'].name['@language']).toBe(scenario.locale);
  168 |         expect(schema.mainEntityOfPage.inLanguage).toBe(scenario.locale);
  169 |         expect(schema.inLanguage).toBeUndefined();
  170 |       }
  171 |     } finally {
  172 |       await harness.stop();
  173 |     }
  174 |   });
  175 |   // Root correction R5: independent general/itinerary transition in both
  176 |   // ES and IT, with served-response assertions, actual lang attributes,
  177 |   // stable URLs, localized controls and stable guide codes.
  178 |   test('ES/IT SSR keeps current general content while the itinerary falls back, then shows ready on reload', async ({ page, request }) => {
  179 |     test.setTimeout(180000);
  180 |     const slug = 'hidden-gems-rome-walking-tour';
  181 |     const fixtureResponse = await request.get(`/api/public/tours/${slug}?locale=en`);
  182 |     expect(fixtureResponse.ok()).toBeTruthy();
  183 |     const fixture = (await fixtureResponse.json()).data as TourDetail;
  184 |     let current = structuredClone(fixture);
  185 |     const harness = await startSsrHarness(slug, () => current);
  186 |     const { mockPort, nextPort } = harness;
  187 | 
  188 |     try {
  189 |       for (const scenario of [
  190 |         { locale: 'es' as const, title: 'Título español actual', description: 'Descripción española actual.', day: 'Llegada española', contentNotice: 'El contenido se muestra en inglés', itineraryNotice: 'El itinerario se muestra en inglés', guide: 'Guía en vivo' },
  191 |         { locale: 'it' as const, title: 'Titolo italiano attuale', description: 'Descrizione italiana attuale.', day: 'Arrivo italiano', contentNotice: 'Il contenuto è mostrato in inglese', itineraryNotice: "L'itinerario è mostrato in inglese", guide: 'Guida dal vivo' },
  192 |       ]) {
  193 |         // Phase 1: current requested general content, unavailable requested
  194 |         // itinerary -> independent English itinerary fallback with its own
  195 |         // notice; general content stays requested with no content notice.
  196 |         const source = structuredClone(fixture);
  197 |         source.title = scenario.title;
  198 |         source.description = scenario.description;
  199 |         source.content_locale = scenario.locale;
  200 |         source.itinerary_locale = 'en';
  201 |         source.itinerary = [{ day: 1, title: 'English arrival', stops: [{ title: 'Meet the guide' }] }];
  202 |         source.guide_languages = ['de', 'en', 'es'];
  203 |         source.languages = source.guide_languages;
  204 |         source.translation_status = 'ready';
  205 |         source.translation_warning = 'partial_translation';
  206 |         source.images = [];
  207 |         source.operator = null;
  208 |         source.related_tours = [];
  209 |         source.reviews = { average_rating: 0, count: 0, distribution: {} };
  210 |         source.rating = { average: 0, count: 0 };
  211 |         source.availability = { ...source.availability, available_dates: [], next_available_date: null, is_unavailable: true };
  212 |         source.seo = { ...source.seo, meta_title: `${source.title} | Bookly`, meta_description: source.description };
  213 |         current = source;
  214 | 
  215 |         const servedFallback = await readServedDetail(mockPort, slug, scenario.locale);
  216 |         expect(servedFallback.content_locale).toBe(scenario.locale);
  217 |         expect(servedFallback.itinerary_locale).toBe('en');
  218 |         expect(servedFallback.translation_status).toBe('ready');
  219 |         expect(servedFallback.translation_warning).toBe('partial_translation');
  220 |         expect(servedFallback.guide_languages).toEqual(['de', 'en', 'es']);
  221 | 
  222 |         const url = `http://127.0.0.1:${nextPort}/${scenario.locale}/tours/${slug}`;
  223 |         await page.goto(url);
  224 |         const fallbackHeading = page.getByRole('heading', { level: 1 });
  225 |         await expect(fallbackHeading).toHaveText(source.title);
  226 |         expect(page.url()).toBe(url);
  227 |         await expect(page.getByText(scenario.itineraryNotice)).toBeVisible();
  228 |         await expect(page.getByText(scenario.contentNotice)).toHaveCount(0);
  229 |         await expect(fallbackHeading).toHaveAttribute('lang', scenario.locale);
  230 |         await expect(page.locator('#itinerary')).toContainText('English arrival');
  231 |         await expect(page.locator('#itinerary h3 span').first()).toHaveAttribute('lang', 'en');
```