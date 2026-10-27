# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> it detail keeps localized booking controls and a real offered date
- Location: tests/e2e/tour-detail.spec.ts:491:9

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('heading', { name: 'Seleziona Data' })
Expected: visible
Timeout: 20000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 20000ms
  - waiting for getByRole('heading', { name: 'Seleziona Data' })

```

```yaml
- banner:
  - link "Vai al contenuto principale":
    - /url: "#main-content"
  - link "Bookly":
    - /url: /it
  - navigation "Navigazione principale":
    - link "Home":
      - /url: /it
    - link "Cerca tour":
      - /url: /it/search
    - link "Categorie":
      - /url: /it/categories
    - link "Destinazioni":
      - /url: /it/destinations
  - button "Cerca tour"
  - combobox "Cambia lingua":
    - option "English"
    - option "Español"
    - option "Italiano" [selected]
  - link "Accedi":
    - /url: /it/auth/login
  - link "Registrati":
    - /url: /it/auth/register
- main:
  - navigation "Percorso di navigazione":
    - list:
      - listitem:
        - link "Home":
          - /url: /it
      - listitem:
        - link "Walking Tours":
          - /url: /it/categories/walking
      - listitem: Gioielli Nascosti di Roma - Tour a Piedi
  - heading "Gioielli Nascosti di Roma - Tour a Piedi" [level=1]
  - button "Salva nella lista desideri"
  - text: "Rome, Italy Valutazione: 4.5 su 5"
  - link "(2 Recensioni)":
    - /url: "#reviews"
  - button "Vedi immagine 1":
    - img "Gioielli Nascosti di Roma - Tour a Piedi"
  - button "Vedi immagine 2":
    - img "Gioielli Nascosti di Roma - Tour a Piedi"
  - button "Vedi immagine 3":
    - img "Gioielli Nascosti di Roma - Tour a Piedi"
  - button "Vedi immagine 4":
    - img "Gioielli Nascosti di Roma - Tour a Piedi"
  - button "Vedi immagine 5":
    - img "Gioielli Nascosti di Roma - Tour a Piedi"
  - button "Vedi tutte le 5 foto"
  - navigation "Sezioni del tour":
    - link "Informazioni sul Tour":
      - /url: "#overview"
    - link "Dettagli del tour":
      - /url: "#facts"
    - link "Punti Salienti":
      - /url: "#highlights"
    - link "Cosa è Incluso":
      - /url: "#included"
    - link "Cosa è Escluso":
      - /url: "#excluded"
    - link "Punto di Incontro":
      - /url: "#meeting"
    - link "Politica di Cancellazione":
      - /url: "#cancellation"
    - link "Recensioni":
      - /url: "#reviews"
    - link "Altre esperienze da scoprire":
      - /url: "#related"
  - article:
    - heading "Informazioni sul Tour" [level=2]
    - text: Scopri gli angoli segreti di Roma che la maggior parte dei turisti non vede mai.
    - heading "Dettagli del tour" [level=2]
    - term: Durata
    - definition: 3 hours
    - term: Dimensione del gruppo
    - definition: 1–20 viaggiatori
    - heading "Punti Salienti" [level=2]
    - list:
      - listitem: Corti nascoste
      - listitem: Botteghe artigiane
      - listitem: Storie romane antiche
    - heading "Cosa è Incluso" [level=3]:
      - button "Cosa è Incluso"
    - heading "Cosa è Escluso" [level=3]:
      - button "Cosa è Escluso"
    - heading "Punto di Incontro" [level=2]
    - paragraph: Entrata principale del Colosseo
    - heading "Politica di Cancellazione" [level=2]
    - paragraph: Cancellazione gratuita fino a 24 ore prima.
    - region "Recensioni":
      - heading "Recensioni" [level=2]
      - heading "Recensioni" [level=3]
      - text: "4.5"
      - 'img "Valutazione: 4.5 su 5"'
      - paragraph: 2 recensioni
      - text: Test Modificato
      - 'img "Valutazione: 4 su 5"'
      - text: 27 settembre 2026
      - paragraph: Updated comment value here.
      - text: Test
      - 'img "Valutazione: 5 su 5"'
      - text: 12 settembre 2026
      - paragraph: A wonderful hidden-gems walk through Rome — highly recommended!
  - complementary:
    - text: 45,00 € / persona Seleziona Data
    - paragraph: Prossimo disponibile
    - paragraph: 17/10/2026
    - combobox "Seleziona Data":
      - option "Seleziona Data"
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
    - text: Partecipanti
    - group "Partecipanti":
      - button "Riduci partecipanti" [disabled]: "-"
      - status: "1"
      - button "Aumenta partecipanti": +
      - text: 1–20 consentiti
    - paragraph: "Totale stimato: 45,00 €"
    - paragraph: Cancellazione gratuita fino a 24 ore prima.
    - paragraph: Accedi per continuare con la prenotazione. Tour, data, numero di viaggiatori e lingua vengono conservati e nulla viene prenotato finché non confermi.
    - link "Prenota Ora":
      - /url: /it/auth/login?returnUrl=%2Fit%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
    - link "Crea account":
      - /url: /it/auth/register?returnUrl=%2Fit%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
  - region "Altre esperienze da scoprire":
    - heading "Altre esperienze da scoprire" [level=2]
    - 'link "Venice Hidden Canals Walking Tour Prossima data: 17 ott 2026 Venice, Italy 2.5 hours Venice Hidden Canals Walking Tour Walking Tours €55.00"':
      - /url: /it/tours/venice-walking-tour-hidden-canals
      - img "Venice Hidden Canals Walking Tour"
      - text: "Prossima data: 17 ott 2026 Venice, Italy 2.5 hours"
      - heading "Venice Hidden Canals Walking Tour" [level=2]
      - text: Walking Tours €55.00
    - button "Salva nella lista desideri"
    - 'link "Barcelona Gaudí Architecture Masterpieces Tour Prossima data: 17 ott 2026 Barcelona, Spain 4 hours Barcelona Gaudí Architecture Masterpieces Tour Walking Tours €65.00"':
      - /url: /it/tours/barcelona-gaudi-architecture-tour
      - img "Barcelona Gaudí Architecture Masterpieces Tour"
      - text: "Prossima data: 17 ott 2026 Barcelona, Spain 4 hours"
      - heading "Barcelona Gaudí Architecture Masterpieces Tour" [level=2]
      - text: Walking Tours €65.00
    - button "Salva nella lista desideri"
    - 'link "Montmartre Artists Quarter Walking Tour Prossima data: 17 ott 2026 Paris, France 2.5 hours Montmartre Artists Quarter Walking Tour Walking Tours €49.00"':
      - /url: /it/tours/paris-montmartre-artists-quarter
      - img "Montmartre Artists Quarter Walking Tour"
      - text: "Prossima data: 17 ott 2026 Paris, France 2.5 hours"
      - heading "Montmartre Artists Quarter Walking Tour" [level=2]
      - text: Walking Tours €49.00
    - button "Salva nella lista desideri"
- contentinfo:
  - img "Bookly Travel — Book smarter. Travel better."
  - paragraph: Esplora
  - list:
    - listitem:
      - link "Cerca tour":
        - /url: /it/search
    - listitem:
      - link "Categorie":
        - /url: /it/categories
    - listitem:
      - link "Destinazioni":
        - /url: /it/destinations
  - paragraph: Azienda
  - list:
    - listitem:
      - link "Informativa sulla privacy":
        - /url: /it/privacy
    - listitem:
      - link "Termini di servizio":
        - /url: /it/terms
  - text: © 2026 Bookly. Tutti i diritti riservati.
  - combobox "Cambia lingua":
    - option "English"
    - option "Español"
    - option "Italiano" [selected]
- complementary "Cookie consent":
  - text: Utilizziamo i cookie per migliorare la tua esperienza di navigazione e analizzare il nostro traffico. Facendo clic su "Accetta", acconsenti al nostro uso dei cookie.
  - button "Rifiuta"
  - button "Accetta"
- alert
```

# Test source

```ts
  398 |   test('about section displays description', async ({ page }) => {
  399 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  400 | 
  401 |     const aboutHeading = page.getByRole('heading', { name: 'About This Tour' });
  402 |     await expect(aboutHeading).toBeVisible({ timeout: 10000 });
  403 |   });
  404 | 
  405 |   test('tour detail has proper SEO metadata', async ({ page }) => {
  406 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  407 | 
  408 |     await expect(page).toHaveTitle(/Bookly/);
  409 | 
  410 |     // Check canonical link
  411 |     const canonical = page.locator('link[rel="canonical"]');
  412 |     if (await canonical.isVisible().catch(() => false)) {
  413 |       const href = await canonical.getAttribute('href');
  414 |       expect(href).toContain('/tours/');
  415 |     }
  416 |   });
  417 | 
  418 |   // ─── Responsive Viewport Tests (FR-032, T092) ───
  419 | 
  420 |   const viewports = [
  421 |     { name: 'mobile', width: 375, height: 667 },
  422 |     { name: 'tablet', width: 768, height: 1024 },
  423 |     { name: 'desktop', width: 1280, height: 800 },
  424 |   ];
  425 | 
  426 |   for (const vp of viewports) {
  427 |     test(`tour detail page adapts to ${vp.name} viewport (${vp.width}px) without horizontal scroll`, async ({ page }) => {
  428 |       await page.setViewportSize({ width: vp.width, height: vp.height });
  429 |       await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  430 | 
  431 |       await expect(page.locator('main')).toBeVisible({ timeout: 10000 });
  432 | 
  433 |       const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
  434 |       const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
  435 |       expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  436 |     });
  437 |   }
  438 | 
  439 |   test('tour detail content stacks vertically on mobile', async ({ page }) => {
  440 |     await page.setViewportSize({ width: 375, height: 667 });
  441 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  442 | 
  443 |     await expect(page.locator('main')).toBeVisible({ timeout: 10000 });
  444 | 
  445 |     // Content should not overflow horizontally
  446 |     const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
  447 |     const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
  448 |     expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  449 |   });
  450 | 
  451 |   test('structured data matches the visible tour and does not invent reviews', async ({ page, request }) => {
  452 |     const slug = 'hidden-gems-rome-walking-tour';
  453 |     const response = await request.get(`/api/public/tours/${slug}?locale=en`);
  454 |     expect(response.ok()).toBeTruthy();
  455 |     const { data } = await response.json();
  456 |     await page.goto(`/en/tours/${slug}`);
  457 |     const script = page.locator('script[type="application/ld+json"]').first();
  458 |     const schema = JSON.parse(await script.textContent() ?? '{}');
  459 |     expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
  460 |     expect(schema.name).toBe(await page.getByRole('heading', { level: 1 }).textContent());
  461 |     expect(schema['@context'].name['@language']).toBe('en');
  462 |     expect(schema['@context'].description['@language']).toBe('en');
  463 |     expect(schema.mainEntityOfPage.inLanguage).toBe('en');
  464 |     expect(schema.inLanguage).toBeUndefined();
  465 |     expect(schema.description).toContain('Colosseum main entrance');
  466 |     expect(schema.image).toEqual(expect.arrayContaining([expect.stringMatching(/^https?:\/\//)]));
  467 |     if (data.reviews.count > 0 && data.reviews.average_rating > 0) {
  468 |       expect(schema.aggregateRating.reviewCount).toBe(data.reviews.count);
  469 |       expect(schema.aggregateRating.ratingValue).toBe(data.reviews.average_rating.toFixed(1));
  470 |     } else {
  471 |       expect(schema.aggregateRating).toBeUndefined();
  472 |     }
  473 |   });
  474 | 
  475 |   test('mobile booking action is keyboard reachable and uses a real offered date', async ({ page }) => {
  476 |     await page.setViewportSize({ width: 390, height: 844 });
  477 |     await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  478 |     const action = page.getByRole('link', { name: 'Continue to booking' });
  479 |     await expect(action).toBeVisible();
  480 |     const href = await action.getAttribute('href');
  481 |     expect(href).toMatch(/^\/en\/booking\?tour=hidden-gems-rome-walking-tour&participants=1&date=\d{4}-\d{2}-\d{2}$/);
  482 |     await action.focus();
  483 |     await expect(action).toBeFocused();
  484 |   });
  485 | 
  486 |   for (const [locale, bookNow, selectDate] of [
  487 |     ['en', 'Book Now', 'Select a Date'],
  488 |     ['es', 'Reservar Ahora', 'Seleccionar Fecha'],
  489 |     ['it', 'Prenota Ora', 'Seleziona Data'],
  490 |   ]) {
  491 |     test(`${locale} detail keeps localized booking controls and a real offered date`, async ({ page, request }) => {
  492 |       const slug = 'hidden-gems-rome-walking-tour';
  493 |       const response = await request.get(`/api/public/tours/${slug}?locale=${locale}`);
  494 |       expect(response.ok()).toBeTruthy();
  495 |       const { data } = await response.json();
  496 |       await page.goto(`/${locale}/tours/${slug}`);
  497 |       await expect(page.getByRole('heading', { level: 1 })).toHaveText(data.title);
> 498 |       await expect(page.getByRole('heading', { name: selectDate })).toBeVisible();
      |                                                                     ^ Error: expect(locator).toBeVisible() failed
  499 |       const action = page.getByRole('link', { name: bookNow }).first();
  500 |       await expect(action).toBeVisible();
  501 |       const href = new URL((await action.getAttribute('href'))!, 'http://bookly.test');
  502 |       expect(href.searchParams.get('date')).toBe(data.availability.next_available_date);
  503 |       expect(data.availability.available_dates).toContain(href.searchParams.get('date'));
  504 |     });
  505 |   }
  506 | 
  507 |   // Spec 019 US4: integrated media/difficulty/guide reads from the REAL
  508 |   // backend through a real isolated Next SSR process (never fabricated JSON;
  509 |   // browser route mocking cannot intercept SSR). Writes touch only an owned
  510 |   // disposable fixture on the seeded published tour and restore it in
  511 |   // `finally` via an actual partner PUT. Auth reuses the existing setup
  512 |   // storageState file in a separate browser context; no token is printed.
  513 |   test('US4 public SSR shows real cover-first gallery with EN alt lang=en on ES/IT pages (seeded tour, restored)', async ({
  514 |     page,
  515 |     request,
  516 |     browser,
  517 |     baseURL,
  518 |   }) => {
  519 |     test.setTimeout(180000);
  520 |     const slug = 'hidden-gems-rome-walking-tour';
  521 |     const authedContext = await browser.newContext({ baseURL, storageState: 'tests/.auth/partner.json' });
  522 |     const authedPage = await authedContext.newPage();
  523 |     let tourId = 0;
  524 |     let snapshot: {
  525 |       media: Array<{ url: string; is_cover: boolean; alt_text?: string | null }>;
  526 |       coverUrl: string | null;
  527 |       difficulty: string | null;
  528 |       guides: string[];
  529 |     } | null = null;
  530 |     try {
  531 |       await authedPage.goto('/en/partner/tours');
  532 |       const boot = await request.get(`/api/public/tours/${slug}?locale=en`);
  533 |       expect(boot.ok()).toBeTruthy();
  534 |       const bootData = ((await boot.json()).data) as TourDetail;
  535 |       tourId = bootData.id;
  536 |       // Legacy seeded tours carry no invented difficulty: absent reads null.
  537 |       if (bootData.difficulty_level === null || bootData.difficulty_level === undefined) {
  538 |         expect(bootData.difficulty_level ?? null).toBeNull();
  539 |       }
  540 |       const owned = await apiGet(authedPage, `/api/partner/tours/${tourId}`);
  541 |       if (owned.status !== 200) {
  542 |         throw new Error(
  543 |           `Spec019 US4 seed prerequisite: expected seeded partner@bookly.test to own published tour ${slug} ` +
  544 |             `(partner GET /api/partner/tours/${tourId} returned ${owned.status}). ` +
  545 |             'Root must prepare ownership/seeding instead of inventing state.'
  546 |         );
  547 |       }
  548 |       snapshot = {
  549 |         media: ownedMedia(owned.json).map((row) => ({
  550 |           url: row.url,
  551 |           is_cover: row.is_cover,
  552 |           ...(row.alt_text === undefined ? {} : { alt_text: row.alt_text }),
  553 |         })),
  554 |         coverUrl: ownedCoverUrl(owned.json),
  555 |         difficulty: ownedDifficulty(owned.json),
  556 |         guides: ownedGuideLanguages(owned.json),
  557 |       };
  558 | 
  559 |       const stamp = Date.now().toString(36);
  560 |       const coverUrl = `http://cdn.bookly.test/tours/rome-cover.jpg?us4=${stamp}-cover`;
  561 |       const secondUrl = `http://cdn.bookly.test/tours/rome-gallery-1.jpg?us4=${stamp}-second`;
  562 |       const thirdUrl = `http://cdn.bookly.test/tours/rome-gallery-2.jpg?us4=${stamp}-third`;
  563 |       const coverAltEn = 'US4 harbor sunrise Caf\u00e9';
  564 |       const secondAltEn = 'US4 old street';
  565 |       const fixture = await apiPut(authedPage, `/api/partner/tours/${tourId}`, {
  566 |         media: [
  567 |           { url: coverUrl, is_cover: true, alt_text: coverAltEn },
  568 |           { url: secondUrl, is_cover: false, alt_text: secondAltEn },
  569 |           { url: thirdUrl, is_cover: false, alt_text: null },
  570 |         ],
  571 |         cover_image_url: coverUrl,
  572 |         guide_languages: ['en', 'de'],
  573 |       });
  574 |       if (fixture.status !== 200) {
  575 |         throw new Error(
  576 |           `Spec019 US4 seed prerequisite: fixture PUT on owned seeded tour ${slug} returned ${fixture.status}. ` +
  577 |             'Root must prepare a writable owned fixture instead of inventing state.'
  578 |         );
  579 |       }
  580 | 
  581 |       const scenarios = [
  582 |         { locale: 'en', view1: 'View image 1', dialog: 'Image lightbox', close: 'Close lightbox', view3: 'View image 3', counter3: '3 of 3' },
  583 |         { locale: 'es', view1: 'Ver imagen 1', dialog: 'Galer\u00eda ampliada', close: 'Cerrar galer\u00eda', view3: 'Ver imagen 3', counter3: '3 de 3' },
  584 |         { locale: 'it', view1: 'Vedi immagine 1', dialog: 'Galleria ingrandita', close: 'Chiudi galleria', view3: 'Vedi immagine 3', counter3: '3 di 3' },
  585 |       ] as const;
  586 |       for (const scenario of scenarios) {
  587 |         // Actual backend response the isolated Next server consumes.
  588 |         const servedResponse = await request.get(`/api/public/tours/${slug}?locale=${scenario.locale}`);
  589 |         expect(servedResponse.ok()).toBeTruthy();
  590 |         const served = ((await servedResponse.json()).data) as TourDetail;
  591 |         // Cover first, remaining order, real URLs only.
  592 |         expect(served.images.map((image) => image.url)).toEqual([coverUrl, secondUrl, thirdUrl]);
  593 |         expect(served.images[0].is_cover).toBe(true);
  594 |         // Specific English alt keeps lang=en on every page locale ...
  595 |         expect(served.images[0].alt).toBe(coverAltEn);
  596 |         expect(served.images[0].alt_locale).toBe('en');
  597 |         expect(served.images[1].alt).toBe(secondAltEn);
  598 |         expect(served.images[1].alt_locale).toBe('en');
```