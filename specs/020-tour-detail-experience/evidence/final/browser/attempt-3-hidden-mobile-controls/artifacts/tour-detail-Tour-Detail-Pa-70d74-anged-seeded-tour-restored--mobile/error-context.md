# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> US6 public SSR reads actual edited English source with disclosed EN fallback; money/availability unchanged (seeded tour, restored)
- Location: tests/e2e/tour-detail.spec.ts:704:7

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('link', { name: 'Reservar Ahora' }).first()
Expected: visible
Timeout: 20000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 20000ms
  - waiting for getByRole('link', { name: 'Reservar Ahora' }).first()

```

```yaml
- banner:
  - link "Saltar al contenido principal":
    - /url: "#main-content"
  - link "Bookly":
    - /url: /es
  - button "Buscar tours"
  - link "Iniciar sesion":
    - /url: /es/auth/login
  - button "Abrir menu"
- main:
  - navigation "Ruta de navegación":
    - list:
      - listitem:
        - link "Inicio":
          - /url: /es
      - listitem:
        - link "Walking Tours":
          - /url: /es/categories/walking
      - listitem: US6 authored source mvcgl3ir
  - heading "US6 authored source mvcgl3ir" [level=1]
  - button "Guardar en lista de deseos"
  - text: "Rome, Italy Valoración: 4.5 de 5"
  - link "(2 Reseñas)":
    - /url: "#reviews"
  - paragraph: El contenido se muestra en inglés
  - paragraph: El itinerario se muestra en inglés
  - button "Ver imagen 1":
    - img "US6 authored source mvcgl3ir"
  - button "Ver imagen 2":
    - img "US6 authored source mvcgl3ir"
  - button "Ver imagen 3":
    - img "US6 authored source mvcgl3ir"
  - button "Ver imagen 4":
    - img "US6 authored source mvcgl3ir"
  - button "Ver imagen 5":
    - img "US6 authored source mvcgl3ir"
  - button "Imagen anterior"
  - button "Imagen siguiente"
  - paragraph: 1 de 5
  - navigation "Secciones del tour":
    - link "Sobre Este Tour":
      - /url: "#overview"
    - link "Datos del tour":
      - /url: "#facts"
    - link "Destacados":
      - /url: "#highlights"
    - link "Itinerario":
      - /url: "#itinerary"
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
    - link "Más experiencias para explorar":
      - /url: "#related"
  - article:
    - heading "Sobre Este Tour" [level=2]
    - text: US6 current English source description with real meeting-point facts.
    - heading "Datos del tour" [level=2]
    - term: Duración
    - definition: 3 hours
    - term: Tamaño del grupo
    - definition: 1–20 viajeros
    - heading "Destacados" [level=2]
    - list:
      - listitem: Hidden courtyards
      - listitem: Local artisan shops
      - listitem: Ancient Roman stories
      - listitem: Off the beaten path
    - heading "Itinerario" [level=2]
    - list:
      - listitem:
        - 'heading "Día 7: US6 first authored day" [level=3]'
        - paragraph: US6 English day description
        - list:
          - listitem: US6 walking activity — US6 walk with the guide
          - listitem: US6 rest break
      - listitem:
        - 'heading "Día 7: US6 second authored day" [level=3]'
      - listitem:
        - 'heading "Día 2: US6 ordered out-of-sequence day" [level=3]'
        - list:
          - listitem: US6 photo stop
    - heading "Qué Incluye" [level=3]:
      - button "Qué Incluye"
    - heading "Qué No Incluye" [level=3]:
      - button "Qué No Incluye"
    - heading "Punto de Encuentro" [level=2]
    - paragraph: US6 Authored Gate
    - heading "Política de Cancelación" [level=2]
    - paragraph: Free cancellation up to 24 hours before the tour.
    - region "Reseñas":
      - heading "Reseñas" [level=2]
      - heading "Reseñas" [level=3]
      - text: "4.5"
      - 'img "Valoración: 4.5 de 5"'
      - paragraph: 2 reseñas
      - text: Test Editado
      - 'img "Valoración: 4 de 5"'
      - text: 27 de septiembre de 2026
      - paragraph: Updated comment value here.
      - text: Test
      - 'img "Valoración: 5 de 5"'
      - text: 12 de septiembre de 2026
      - paragraph: A wonderful hidden-gems walk through Rome — highly recommended!
  - complementary:
    - paragraph: 45,00 € / persona 18/10/2026 · 1 · 45,00 €
    - link "Continuar con la reserva":
      - /url: /es/auth/login?returnUrl=%2Fes%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-18
    - button "Editar la selección de reserva"
  - region "Más experiencias para explorar":
    - heading "Más experiencias para explorar" [level=2]
    - 'link "Venice Hidden Canals Walking Tour Próxima fecha: 18 oct 2026 Venice, Italy 2.5 hours Venice Hidden Canals Walking Tour Walking Tours €55.00"':
      - /url: /es/tours/venice-walking-tour-hidden-canals
      - img "Venice Hidden Canals Walking Tour"
      - text: "Próxima fecha: 18 oct 2026 Venice, Italy 2.5 hours"
      - heading "Venice Hidden Canals Walking Tour" [level=2]
      - text: Walking Tours €55.00
    - button "Guardar en lista de deseos"
    - 'link "Barcelona Gaudí Architecture Masterpieces Tour Próxima fecha: 18 oct 2026 Barcelona, Spain 4 hours Barcelona Gaudí Architecture Masterpieces Tour Walking Tours €65.00"':
      - /url: /es/tours/barcelona-gaudi-architecture-tour
      - img "Barcelona Gaudí Architecture Masterpieces Tour"
      - text: "Próxima fecha: 18 oct 2026 Barcelona, Spain 4 hours"
      - heading "Barcelona Gaudí Architecture Masterpieces Tour" [level=2]
      - text: Walking Tours €65.00
    - button "Guardar en lista de deseos"
    - 'link "Montmartre Artists Quarter Walking Tour Próxima fecha: 18 oct 2026 Paris, France 2.5 hours Montmartre Artists Quarter Walking Tour Walking Tours €49.00"':
      - /url: /es/tours/paris-montmartre-artists-quarter
      - img "Montmartre Artists Quarter Walking Tour"
      - text: "Próxima fecha: 18 oct 2026 Paris, France 2.5 hours"
      - heading "Montmartre Artists Quarter Walking Tour" [level=2]
      - text: Walking Tours €49.00
    - button "Guardar en lista de deseos"
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
- complementary "Cookie consent"
- alert
```

# Test source

```ts
  742 |       const sourceDescription = 'US6 current English source description with real meeting-point facts.';
  743 |       const sourceMeetingPoint = 'US6 Authored Gate';
  744 |       const sourceItinerary: NonNullable<EnglishSourceInput['itinerary']> = [
  745 |         { day: 7, title: 'US6 first authored day', description: 'US6 English day description', stops: [
  746 |           { title: 'US6 walking activity', description: 'US6 walk with the guide', duration_minutes: 30 },
  747 |           { title: 'US6 rest break', description: null, duration_minutes: null },
  748 |         ] },
  749 |         { day: 7, title: 'US6 second authored day', description: null, stops: [] },
  750 |         { day: 2, title: 'US6 ordered out-of-sequence day', description: null, stops: [
  751 |           { title: 'US6 photo stop', description: null, duration_minutes: 15 },
  752 |         ] },
  753 |       ];
  754 |       const write = await apiPut(authedPage, `/api/partner/tours/${tourId}`, {
  755 |         translations: { en: {
  756 |           ...(snapshot as EnglishSourceInput),
  757 |           title: sourceTitle,
  758 |           description: sourceDescription,
  759 |           meeting_point: sourceMeetingPoint,
  760 |           itinerary: sourceItinerary,
  761 |         } },
  762 |       });
  763 |       if (write.status !== 200) {
  764 |         throw new Error(
  765 |           `Spec019 US6 seed prerequisite: source PUT on owned seeded tour ${slug} returned ${write.status}. ` +
  766 |             'Root must prepare a writable owned fixture instead of inventing state.'
  767 |         );
  768 |       }
  769 | 
  770 |       // Actual backend response the isolated Next server consumes: the edited
  771 |       // source is current while the ES derivative (missing or superseded by
  772 |       // the edit) falls back to disclosed English. Server money/booking
  773 |       // availability do not move with a source edit.
  774 |       const servedResponse = await request.get(`/api/public/tours/${slug}?locale=es`);
  775 |       expect(servedResponse.ok()).toBeTruthy();
  776 |       const served = ((await servedResponse.json()).data) as TourDetail;
  777 |       expect(served.title).toBe(sourceTitle);
  778 |       expect(served.description).toBe(sourceDescription);
  779 |       expect(served.content_locale).toBe('en');
  780 |       expect(served.meeting_point).toBe(sourceMeetingPoint);
  781 |       expect(served.itinerary).toEqual(sourceItinerary);
  782 |       expect(served.itinerary_locale).toBe('en');
  783 |       expect(['pending', 'stale', 'failed']).toContain(served.translation_status);
  784 |       expect(served.translation_warning).toBe('partial_translation');
  785 |       expect(served.pricing).toEqual(moneyBefore);
  786 |       expect(served.availability).toEqual(availabilityBefore);
  787 | 
  788 |       const url = new URL(`/es/tours/${slug}`, baseURL).href;
  789 |       await page.goto(url);
  790 |       expect(page.url()).toBe(url);
  791 |       const heading = page.getByRole('heading', { level: 1 });
  792 |       await expect(heading).toHaveText(sourceTitle);
  793 |       await expect(heading).toHaveAttribute('lang', 'en');
  794 |       await expect(page.getByText('El contenido se muestra en inglés')).toBeVisible();
  795 |       await expect(page.getByText('El itinerario se muestra en inglés')).toBeVisible();
  796 |       // Authored order is preserved verbatim (repeated day 7 first, then day
  797 |       // 2): position order, not day-number order.
  798 |       const dayTitles = await page.locator('#itinerary h3 span').evaluateAll((els) => els.map((el) => el.textContent));
  799 |       expect(dayTitles).toEqual(['US6 first authored day', 'US6 second authored day', 'US6 ordered out-of-sequence day']);
  800 |       await expect(page.locator('#itinerary')).toContainText('US6 walking activity');
  801 |       await expect(page.locator('#itinerary')).toContainText('US6 photo stop');
  802 | 
  803 |       // Actual title/meta/canonical/hreflang/language: metadata follows the
  804 |       // selected English source on the ES page; canonical/hreflang keep the
  805 |       // requested slug in every real language.
  806 |       await expect(page).toHaveTitle(`${sourceTitle} | Bookly`);
  807 |       const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
  808 |       expect(canonical).toContain(`/es/tours/${slug}`);
  809 |       for (const lang of ['en', 'es', 'it']) {
  810 |         const href = await page.locator(`link[rel="alternate"][hreflang="${lang}"]`).getAttribute('href');
  811 |         expect(href).toContain(`/${lang}/tours/${slug}`);
  812 |       }
  813 |       const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
  814 |       expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
  815 |       expect(schema.name).toBe(sourceTitle);
  816 |       expect(schema['@context'].name['@language']).toBe('en');
  817 |       expect(schema['@context'].description['@language']).toBe('en');
  818 |       expect(schema.mainEntityOfPage.inLanguage).toBe('en');
  819 |       expect(schema.inLanguage).toBeUndefined();
  820 |       expect(schema.description).toContain(sourceMeetingPoint);
  821 |       // Ordered real days/stops with stable positions despite repeated days.
  822 |       const days = schema.itinerary.itemListElement;
  823 |       expect(days.map((day: { position: number; identifier: number }) => [day.position, day.identifier])).toEqual([
  824 |         [1, 7], [2, 7], [3, 2],
  825 |       ]);
  826 |       expect(days[0].item.itemListElement.map((stop: { position: number; item: { name: string } }) => [stop.position, stop.item.name])).toEqual([
  827 |         [1, 'US6 walking activity'], [2, 'US6 rest break'],
  828 |       ]);
  829 |       expect(schema.tripOrigin).toEqual({ '@type': 'Place', name: sourceMeetingPoint });
  830 |       // No fabricated rating: the seeded review facts decide, never the edit.
  831 |       if (served.reviews.count > 0 && served.reviews.average_rating > 0) {
  832 |         expect(schema.aggregateRating.reviewCount).toBe(served.reviews.count);
  833 |         expect(schema.aggregateRating.ratingValue).toBe(served.reviews.average_rating.toFixed(1));
  834 |       } else {
  835 |         expect(schema.aggregateRating).toBeUndefined();
  836 |       }
  837 |       // Booking stays server-authoritative: the localized CTA deep-links the
  838 |       // real next offered date, or no CTA is offered when unavailable.
  839 |       const bookingAction = page.getByRole('link', { name: 'Reservar Ahora' }).first();
  840 |       if (served.availability.next_available_date !== null) {
  841 |         expect(served.availability.available_dates).toContain(served.availability.next_available_date);
> 842 |         await expect(bookingAction).toBeVisible();
      |                                     ^ Error: expect(locator).toBeVisible() failed
  843 |         const actionHref = new URL((await bookingAction.getAttribute('href'))!, 'http://bookly.test');
  844 |         const href = new URL(actionHref.searchParams.get('returnUrl') ?? actionHref.href, 'http://bookly.test');
  845 |         expect(href.pathname).toBe('/es/booking');
  846 |         expect(href.searchParams.get('tour')).toBe(slug);
  847 |         expect(href.searchParams.get('participants')).toBe(String(served.group_size.min));
  848 |         expect(href.searchParams.get('date')).toBe(served.availability.next_available_date);
  849 |       } else {
  850 |         await expect(bookingAction).toHaveCount(0);
  851 |       }
  852 |     } finally {
  853 |       if (snapshot !== null && tourId !== 0) {
  854 |         const restore = await apiPut(authedPage, `/api/partner/tours/${tourId}`, {
  855 |           translations: { en: snapshot },
  856 |         });
  857 |         expect(restore.status).toBe(200);
  858 |       }
  859 |       await authedContext.close();
  860 |     }
  861 |   });
  862 | 
  863 |   // Spec 020 US1: ordered readable hierarchy through the real built SSR
  864 |   // server. Controlled complete/sparse responses prove prescribed order,
  865 |   // usable disclosure, important-information retention and no orphan nav
  866 |   // entries without touching shared tour data.
  867 |   for (const width of [390, 1440]) {
  868 |     test(`US1 SSR complete tour at ${width}px keeps prescribed order with disclosure and no orphan nav entries`, async ({ page }) => {
  869 |       test.setTimeout(180000);
  870 |       await page.setViewportSize({ width, height: 900 });
  871 |       const slug = 'hidden-gems-rome-walking-tour';
  872 |       const complete = createFullTourDetail(new Date('2026-10-14T00:00:00.000Z'));
  873 |       complete.description = `${'Tuscan sun. '.repeat(80)}Final reachable sentence.`;
  874 |       const harness = await startSsrHarness(slug, () => complete);
  875 |       try {
  876 |         await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  877 |         const served = await readServedDetail(harness.mockPort, slug, 'en');
  878 |         expect(served.title).toBe(complete.title);
  879 |         expect(served.reviews.count).toBeGreaterThan(0);
  880 | 
  881 |         await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  882 | 
  883 |         // Header: breadcrumbs, title, location and genuine rating/review link.
  884 |         await expect(page.getByRole('navigation', { name: 'Breadcrumbs' })).toBeVisible();
  885 |         await expect(page.getByRole('heading', { level: 1 })).toHaveText(complete.title);
  886 |         await expect(page.getByText('Rome, Italy').first()).toBeVisible();
  887 |         const reviewLinks = page.locator('a[href="#reviews"]');
  888 |         await expect(reviewLinks).toHaveCount(2);
  889 | 
  890 |         // Prescribed section order in the served document.
  891 |         const ids = ['overview', 'facts', 'highlights', 'itinerary', 'included', 'excluded', 'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related'];
  892 |         const ordered = await page.evaluate((wanted: string[]) => {
  893 |           const elements = wanted.map((id) => document.getElementById(id));
  894 |           if (elements.some((element) => element === null)) return false;
  895 |           return elements.every((element, index) =>
  896 |             index === 0 || elements[index - 1]!.compareDocumentPosition(element!) === 4,
  897 |           );
  898 |         }, ids);
  899 |         expect(ordered).toBe(true);
  900 | 
  901 |         // Navigation targets exactly the rendered sections.
  902 |         const nav = page.getByRole('navigation', { name: 'Tour sections' });
  903 |         const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
  904 |         expect(hrefs).toEqual(ids.map((id) => `#${id}`));
  905 |         for (const href of hrefs) {
  906 |           await expect(page.locator(href!)).toHaveCount(1);
  907 |         }
  908 | 
  909 |         // Long overview: preview first, full text reachable through disclosure.
  910 |         await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
  911 |         const expand = page.getByRole('button', { name: 'Read more' });
  912 |         await expect(expand).toHaveAttribute('aria-expanded', 'false');
  913 |         expect(await expand.getAttribute('aria-controls')).toBeTruthy();
  914 |         await expand.click();
  915 |         await expect(page.getByText('Final reachable sentence.')).toBeVisible();
  916 |         const collapse = page.getByRole('button', { name: 'Read less' });
  917 |         await expect(collapse).toHaveAttribute('aria-expanded', 'true');
  918 |         await collapse.click();
  919 |         await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
  920 | 
  921 |         // Practical information stays fully readable; genuine facts shown.
  922 |         await expect(page.locator('#important')).toContainText('Bring your passport');
  923 |         await expect(page.locator('#facts')).toContainText('Moderate');
  924 |         await expect(page.locator('#itinerary')).toContainText('Artisan quarter');
  925 |         await page.screenshot({ path: `../specs/020-tour-detail-experience/evidence/us1/visual/complete-en-${width}.png`, fullPage: true });
  926 |       } finally {
  927 |         await harness.stop();
  928 |       }
  929 |     });
  930 | 
  931 |     test(`US1 SSR sparse tour at ${width}px omits absent sections, entries and review claims`, async ({ page }) => {
  932 |       test.setTimeout(180000);
  933 |       await page.setViewportSize({ width, height: 900 });
  934 |       const slug = 'hidden-gems-rome-walking-tour';
  935 |       const sparse = createSparseTourDetail(new Date('2026-10-14T00:00:00.000Z'));
  936 |       const harness = await startSsrHarness(slug, () => sparse);
  937 |       try {
  938 |         await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  939 |         const served = await readServedDetail(harness.mockPort, slug, 'en');
  940 |         expect(served.reviews.count).toBe(0);
  941 | 
  942 |         await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
```