# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> US6 public SSR reads actual edited English source with disclosed EN fallback; money/availability unchanged (seeded tour, restored)
- Location: tests\e2e\tour-detail.spec.ts:680:7

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: "2026-10-17"
Received: null
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
              - generic [ref=e34]: US6 authored source mvb8pv67
        - heading "US6 authored source mvb8pv67" [level=1] [ref=e35]
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
        - paragraph [ref=e49]: El contenido se muestra en inglés
        - paragraph [ref=e50]: El itinerario se muestra en inglés
      - generic [ref=e51]:
        - generic [ref=e52]:
          - button "Ver imagen 1" [ref=e53]:
            - img "US6 authored source mvb8pv67" [ref=e54]
          - button "Ver imagen 2" [ref=e55]:
            - img "US6 authored source mvb8pv67" [ref=e56]
          - button "Ver imagen 3" [ref=e57]:
            - img "US6 authored source mvb8pv67" [ref=e58]
          - button "Ver imagen 4" [ref=e59]:
            - img "US6 authored source mvb8pv67" [ref=e60]
          - button "Ver imagen 5" [ref=e61]:
            - img "US6 authored source mvb8pv67" [ref=e62]
        - button "Ver las 5 fotos" [ref=e63]
      - navigation "Secciones del tour" [ref=e64]:
        - generic [ref=e65]:
          - link "Sobre Este Tour" [ref=e66] [cursor=pointer]:
            - /url: "#overview"
          - link "Datos del tour" [ref=e67] [cursor=pointer]:
            - /url: "#facts"
          - link "Destacados" [ref=e68] [cursor=pointer]:
            - /url: "#highlights"
          - link "Itinerario" [ref=e69] [cursor=pointer]:
            - /url: "#itinerary"
          - link "Qué Incluye" [ref=e70] [cursor=pointer]:
            - /url: "#included"
          - link "Qué No Incluye" [ref=e71] [cursor=pointer]:
            - /url: "#excluded"
          - link "Punto de Encuentro" [ref=e72] [cursor=pointer]:
            - /url: "#meeting"
          - link "Política de Cancelación" [ref=e73] [cursor=pointer]:
            - /url: "#cancellation"
          - link "Reseñas" [ref=e74] [cursor=pointer]:
            - /url: "#reviews"
      - generic [ref=e75]:
        - article [ref=e76]:
          - generic [ref=e77]:
            - heading "Sobre Este Tour" [level=2] [ref=e78]
            - generic [ref=e79]: US6 current English source description with real meeting-point facts.
          - generic [ref=e80]:
            - heading "Datos del tour" [level=2] [ref=e81]
            - generic [ref=e82]:
              - generic [ref=e83]:
                - term [ref=e84]: Duración
                - definition [ref=e85]: 3 hours
              - generic [ref=e86]:
                - term [ref=e87]: Tamaño del grupo
                - definition [ref=e88]: 1–20 viajeros
          - generic [ref=e89]:
            - heading "Destacados" [level=2] [ref=e90]
            - list [ref=e91]:
              - listitem [ref=e92]:
                - img [ref=e93]
                - text: Hidden courtyards
              - listitem [ref=e95]:
                - img [ref=e96]
                - text: Local artisan shops
              - listitem [ref=e98]:
                - img [ref=e99]
                - text: Ancient Roman stories
              - listitem [ref=e101]:
                - img [ref=e102]
                - text: Off the beaten path
          - generic [ref=e104]:
            - heading "Itinerario" [level=2] [ref=e105]
            - list [ref=e106]:
              - listitem [ref=e107]:
                - 'heading "Día 7: US6 first authored day" [level=3] [ref=e108]'
                - paragraph [ref=e109]: US6 English day description
                - list [ref=e110]:
                  - listitem [ref=e111]: US6 walking activity — US6 walk with the guide
                  - listitem [ref=e112]: US6 rest break
              - listitem [ref=e113]:
                - 'heading "Día 7: US6 second authored day" [level=3] [ref=e114]'
              - listitem [ref=e115]:
                - 'heading "Día 2: US6 ordered out-of-sequence day" [level=3] [ref=e116]'
                - list [ref=e117]:
                  - listitem [ref=e118]: US6 photo stop
          - generic [ref=e119]:
            - heading "Qué Incluye" [level=3] [ref=e123]:
              - button "Qué Incluye" [ref=e124]:
                - text: Qué Incluye
                - img [ref=e125]
            - heading "Qué No Incluye" [level=3] [ref=e130]:
              - button "Qué No Incluye" [ref=e131]:
                - text: Qué No Incluye
                - img [ref=e132]
          - generic [ref=e134]:
            - heading "Punto de Encuentro" [level=2] [ref=e135]
            - paragraph [ref=e136]:
              - img [ref=e137]
              - text: US6 Authored Gate
          - generic [ref=e140]:
            - heading "Política de Cancelación" [level=2] [ref=e141]
            - paragraph [ref=e142]: Free cancellation up to 24 hours before the tour.
          - generic [ref=e144]:
            - heading "Reseñas" [level=3] [ref=e145]
            - generic [ref=e147]:
              - generic "Average rating 4.5 out of 5" [ref=e148]: "4.5"
              - generic [ref=e149]:
                - radiogroup "Star rating" [ref=e150]:
                  - radio "1 star" [disabled] [ref=e151]:
                    - img [ref=e152]
                  - radio "2 stars" [disabled] [ref=e154]:
                    - img [ref=e155]
                  - radio "3 stars" [disabled] [ref=e157]:
                    - img [ref=e158]
                  - radio "4 stars" [disabled] [ref=e160]:
                    - img [ref=e161]
                  - radio "5 stars" [checked] [disabled] [ref=e163]:
                    - img [ref=e164]
                - paragraph [ref=e166]: 2 reseñas
            - generic [ref=e167]:
              - generic [ref=e168]:
                - generic [ref=e169]:
                  - generic [ref=e170]: Test
                  - generic [ref=e171]: Edited
                - generic [ref=e172]:
                  - radiogroup "Star rating" [ref=e173]:
                    - radio "1 star" [disabled] [ref=e174]:
                      - img [ref=e175]
                    - radio "2 stars" [disabled] [ref=e177]:
                      - img [ref=e178]
                    - radio "3 stars" [disabled] [ref=e180]:
                      - img [ref=e181]
                    - radio "4 stars" [checked] [disabled] [ref=e183]:
                      - img [ref=e184]
                    - radio "5 stars" [disabled] [ref=e186]:
                      - img [ref=e187]
                  - generic [ref=e189]: 28 de septiembre de 2026
                - paragraph [ref=e190]: Updated comment value here.
              - generic [ref=e191]:
                - generic [ref=e193]: Test
                - generic [ref=e194]:
                  - radiogroup "Star rating" [ref=e195]:
                    - radio "1 star" [disabled] [ref=e196]:
                      - img [ref=e197]
                    - radio "2 stars" [disabled] [ref=e199]:
                      - img [ref=e200]
                    - radio "3 stars" [disabled] [ref=e202]:
                      - img [ref=e203]
                    - radio "4 stars" [disabled] [ref=e205]:
                      - img [ref=e206]
                    - radio "5 stars" [checked] [disabled] [ref=e208]:
                      - img [ref=e209]
                  - generic [ref=e211]: 12 de septiembre de 2026
                - paragraph [ref=e212]: A wonderful hidden-gems walk through Rome — highly recommended!
        - complementary [ref=e213]:
          - generic [ref=e215]:
            - generic [ref=e216]:
              - generic [ref=e217]:
                - text: 45,00 €
                - generic [ref=e218]: / persona
              - button "Guardar en lista de deseos" [ref=e219]:
                - generic [ref=e220]: ♡
            - generic [ref=e221]:
              - generic [ref=e222]: Seleccionar Fecha
              - generic [ref=e223]:
                - paragraph [ref=e224]: Próximo disponible
                - paragraph [ref=e225]: 17/10/2026
              - combobox "Seleccionar Fecha" [ref=e226]:
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
            - generic [ref=e227]:
              - generic [ref=e228]: Participantes
              - group "Participantes" [ref=e229]:
                - button "Reducir participantes" [disabled] [ref=e230]: "-"
                - status [ref=e231]: "1"
                - button "Aumentar participantes" [ref=e232]: +
                - generic [ref=e233]: 1–20 permitidos
            - paragraph [ref=e234]: "Total estimado: 45,00 €"
            - paragraph [ref=e235]: Free cancellation up to 24 hours before the tour.
            - generic [ref=e236]:
              - paragraph [ref=e237]: Inicia sesión para continuar con la reserva. Tu tour, fecha, número de viajeros e idioma se conservan y no se reserva nada hasta que confirmes.
              - link "Reservar Ahora" [ref=e238] [cursor=pointer]:
                - /url: /es/auth/login?returnUrl=%2Fes%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
              - link "Crear cuenta" [ref=e239] [cursor=pointer]:
                - /url: /es/auth/register?returnUrl=%2Fes%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
  - contentinfo [ref=e240]:
    - generic [ref=e241]:
      - generic [ref=e242]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e245]
        - generic [ref=e246]:
          - paragraph [ref=e247]: Explorar
          - list [ref=e248]:
            - listitem [ref=e249]:
              - link "Buscar tours" [ref=e250] [cursor=pointer]:
                - /url: /es/search
            - listitem [ref=e251]:
              - link "Categorías" [ref=e252] [cursor=pointer]:
                - /url: /es/categories
            - listitem [ref=e253]:
              - link "Destinos" [ref=e254] [cursor=pointer]:
                - /url: /es/destinations
        - generic [ref=e255]:
          - paragraph [ref=e256]: Empresa
          - list [ref=e257]:
            - listitem [ref=e258]:
              - link "Política de privacidad" [ref=e259] [cursor=pointer]:
                - /url: /es/privacy
            - listitem [ref=e260]:
              - link "Términos del servicio" [ref=e261] [cursor=pointer]:
                - /url: /es/terms
      - generic [ref=e262]:
        - generic [ref=e263]: © 2026 Bookly. Todos los derechos reservados.
        - combobox "Cambiar idioma" [ref=e265]:
          - option "English"
          - option "Español" [selected]
          - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e266]:
      - generic [ref=e267]: Usamos cookies para mejorar tu experiencia de navegación y analizar nuestro tráfico. Al hacer clic en "Aceptar", aceptas nuestro uso de cookies.
      - generic [ref=e268]:
        - button "Rechazar" [ref=e269]
        - button "Aceptar" [ref=e270]
  - alert [ref=e271]
```

# Test source

```ts
  720 |       const sourceItinerary: NonNullable<EnglishSourceInput['itinerary']> = [
  721 |         { day: 7, title: 'US6 first authored day', description: 'US6 English day description', stops: [
  722 |           { title: 'US6 walking activity', description: 'US6 walk with the guide', duration_minutes: 30 },
  723 |           { title: 'US6 rest break', description: null, duration_minutes: null },
  724 |         ] },
  725 |         { day: 7, title: 'US6 second authored day', description: null, stops: [] },
  726 |         { day: 2, title: 'US6 ordered out-of-sequence day', description: null, stops: [
  727 |           { title: 'US6 photo stop', description: null, duration_minutes: 15 },
  728 |         ] },
  729 |       ];
  730 |       const write = await apiPut(authedPage, `/api/partner/tours/${tourId}`, {
  731 |         translations: { en: {
  732 |           ...(snapshot as EnglishSourceInput),
  733 |           title: sourceTitle,
  734 |           description: sourceDescription,
  735 |           meeting_point: sourceMeetingPoint,
  736 |           itinerary: sourceItinerary,
  737 |         } },
  738 |       });
  739 |       if (write.status !== 200) {
  740 |         throw new Error(
  741 |           `Spec019 US6 seed prerequisite: source PUT on owned seeded tour ${slug} returned ${write.status}. ` +
  742 |             'Root must prepare a writable owned fixture instead of inventing state.'
  743 |         );
  744 |       }
  745 | 
  746 |       // Actual backend response the isolated Next server consumes: the edited
  747 |       // source is current while the ES derivative (missing or superseded by
  748 |       // the edit) falls back to disclosed English. Server money/booking
  749 |       // availability do not move with a source edit.
  750 |       const servedResponse = await request.get(`/api/public/tours/${slug}?locale=es`);
  751 |       expect(servedResponse.ok()).toBeTruthy();
  752 |       const served = ((await servedResponse.json()).data) as TourDetail;
  753 |       expect(served.title).toBe(sourceTitle);
  754 |       expect(served.description).toBe(sourceDescription);
  755 |       expect(served.content_locale).toBe('en');
  756 |       expect(served.meeting_point).toBe(sourceMeetingPoint);
  757 |       expect(served.itinerary).toEqual(sourceItinerary);
  758 |       expect(served.itinerary_locale).toBe('en');
  759 |       expect(['pending', 'stale', 'failed']).toContain(served.translation_status);
  760 |       expect(served.translation_warning).toBe('partial_translation');
  761 |       expect(served.pricing).toEqual(moneyBefore);
  762 |       expect(served.availability).toEqual(availabilityBefore);
  763 | 
  764 |       const url = new URL(`/es/tours/${slug}`, baseURL).href;
  765 |       await page.goto(url);
  766 |       expect(page.url()).toBe(url);
  767 |       const heading = page.getByRole('heading', { level: 1 });
  768 |       await expect(heading).toHaveText(sourceTitle);
  769 |       await expect(heading).toHaveAttribute('lang', 'en');
  770 |       await expect(page.getByText('El contenido se muestra en inglés')).toBeVisible();
  771 |       await expect(page.getByText('El itinerario se muestra en inglés')).toBeVisible();
  772 |       // Authored order is preserved verbatim (repeated day 7 first, then day
  773 |       // 2): position order, not day-number order.
  774 |       const dayTitles = await page.locator('#itinerary h3 span').evaluateAll((els) => els.map((el) => el.textContent));
  775 |       expect(dayTitles).toEqual(['US6 first authored day', 'US6 second authored day', 'US6 ordered out-of-sequence day']);
  776 |       await expect(page.locator('#itinerary')).toContainText('US6 walking activity');
  777 |       await expect(page.locator('#itinerary')).toContainText('US6 photo stop');
  778 | 
  779 |       // Actual title/meta/canonical/hreflang/language: metadata follows the
  780 |       // selected English source on the ES page; canonical/hreflang keep the
  781 |       // requested slug in every real language.
  782 |       await expect(page).toHaveTitle(`${sourceTitle} | Bookly`);
  783 |       const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
  784 |       expect(canonical).toContain(`/es/tours/${slug}`);
  785 |       for (const lang of ['en', 'es', 'it']) {
  786 |         const href = await page.locator(`link[rel="alternate"][hreflang="${lang}"]`).getAttribute('href');
  787 |         expect(href).toContain(`/${lang}/tours/${slug}`);
  788 |       }
  789 |       const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
  790 |       expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
  791 |       expect(schema.name).toBe(sourceTitle);
  792 |       expect(schema['@context'].name['@language']).toBe('en');
  793 |       expect(schema['@context'].description['@language']).toBe('en');
  794 |       expect(schema.mainEntityOfPage.inLanguage).toBe('en');
  795 |       expect(schema.inLanguage).toBeUndefined();
  796 |       expect(schema.description).toContain(sourceMeetingPoint);
  797 |       // Ordered real days/stops with stable positions despite repeated days.
  798 |       const days = schema.itinerary.itemListElement;
  799 |       expect(days.map((day: { position: number; identifier: number }) => [day.position, day.identifier])).toEqual([
  800 |         [1, 7], [2, 7], [3, 2],
  801 |       ]);
  802 |       expect(days[0].item.itemListElement.map((stop: { position: number; item: { name: string } }) => [stop.position, stop.item.name])).toEqual([
  803 |         [1, 'US6 walking activity'], [2, 'US6 rest break'],
  804 |       ]);
  805 |       expect(schema.tripOrigin).toEqual({ '@type': 'Place', name: sourceMeetingPoint });
  806 |       // No fabricated rating: the seeded review facts decide, never the edit.
  807 |       if (served.reviews.count > 0 && served.reviews.average_rating > 0) {
  808 |         expect(schema.aggregateRating.reviewCount).toBe(served.reviews.count);
  809 |         expect(schema.aggregateRating.ratingValue).toBe(served.reviews.average_rating.toFixed(1));
  810 |       } else {
  811 |         expect(schema.aggregateRating).toBeUndefined();
  812 |       }
  813 |       // Booking stays server-authoritative: the localized CTA deep-links the
  814 |       // real next offered date, or no CTA is offered when unavailable.
  815 |       const bookingAction = page.getByRole('link', { name: 'Reservar Ahora' }).first();
  816 |       if (served.availability.next_available_date !== null) {
  817 |         expect(served.availability.available_dates).toContain(served.availability.next_available_date);
  818 |         await expect(bookingAction).toBeVisible();
  819 |         const href = new URL((await bookingAction.getAttribute('href'))!, 'http://bookly.test');
> 820 |         expect(href.searchParams.get('date')).toBe(served.availability.next_available_date);
      |                                               ^ Error: expect(received).toBe(expected) // Object.is equality
  821 |       } else {
  822 |         await expect(bookingAction).toHaveCount(0);
  823 |       }
  824 |     } finally {
  825 |       if (snapshot !== null && tourId !== 0) {
  826 |         const restore = await apiPut(authedPage, `/api/partner/tours/${tourId}`, {
  827 |           translations: { en: snapshot },
  828 |         });
  829 |         expect(restore.status).toBe(200);
  830 |       }
  831 |       await authedContext.close();
  832 |     }
  833 |   });
  834 | 
  835 |   // Spec 020 US1: ordered readable hierarchy through the real built SSR
  836 |   // server. Controlled complete/sparse responses prove prescribed order,
  837 |   // usable disclosure, important-information retention and no orphan nav
  838 |   // entries without touching shared tour data.
  839 |   for (const width of [390, 1440]) {
  840 |     test(`US1 SSR complete tour at ${width}px keeps prescribed order with disclosure and no orphan nav entries`, async ({ page }) => {
  841 |       test.setTimeout(180000);
  842 |       await page.setViewportSize({ width, height: 900 });
  843 |       const slug = 'hidden-gems-rome-walking-tour';
  844 |       const complete = createFullTourDetail(new Date('2026-10-14T00:00:00.000Z'));
  845 |       complete.description = `${'Tuscan sun. '.repeat(80)}Final reachable sentence.`;
  846 |       const harness = await startSsrHarness(slug, () => complete);
  847 |       try {
  848 |         await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  849 |         const served = await readServedDetail(harness.mockPort, slug, 'en');
  850 |         expect(served.title).toBe(complete.title);
  851 |         expect(served.reviews.count).toBeGreaterThan(0);
  852 | 
  853 |         await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  854 | 
  855 |         // Header: breadcrumbs, title, location and genuine rating/review link.
  856 |         await expect(page.getByRole('navigation', { name: 'Breadcrumbs' })).toBeVisible();
  857 |         await expect(page.getByRole('heading', { level: 1 })).toHaveText(complete.title);
  858 |         await expect(page.getByText('Rome, Italy').first()).toBeVisible();
  859 |         const reviewLinks = page.locator('a[href="#reviews"]');
  860 |         await expect(reviewLinks).toHaveCount(2);
  861 | 
  862 |         // Prescribed section order in the served document.
  863 |         const ids = ['overview', 'facts', 'highlights', 'itinerary', 'included', 'excluded', 'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related'];
  864 |         const ordered = await page.evaluate((wanted: string[]) => {
  865 |           const elements = wanted.map((id) => document.getElementById(id));
  866 |           if (elements.some((element) => element === null)) return false;
  867 |           return elements.every((element, index) =>
  868 |             index === 0 || elements[index - 1]!.compareDocumentPosition(element!) === 4,
  869 |           );
  870 |         }, ids);
  871 |         expect(ordered).toBe(true);
  872 | 
  873 |         // Navigation targets exactly the rendered sections.
  874 |         const nav = page.getByRole('navigation', { name: 'Tour sections' });
  875 |         const hrefs = await nav.getByRole('link').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
  876 |         expect(hrefs).toEqual(ids.map((id) => `#${id}`));
  877 |         for (const href of hrefs) {
  878 |           await expect(page.locator(href!)).toHaveCount(1);
  879 |         }
  880 | 
  881 |         // Long overview: preview first, full text reachable through disclosure.
  882 |         await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
  883 |         const expand = page.getByRole('button', { name: 'Read more' });
  884 |         await expect(expand).toHaveAttribute('aria-expanded', 'false');
  885 |         expect(await expand.getAttribute('aria-controls')).toBeTruthy();
  886 |         await expand.click();
  887 |         await expect(page.getByText('Final reachable sentence.')).toBeVisible();
  888 |         const collapse = page.getByRole('button', { name: 'Read less' });
  889 |         await expect(collapse).toHaveAttribute('aria-expanded', 'true');
  890 |         await collapse.click();
  891 |         await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
  892 | 
  893 |         // Practical information stays fully readable; genuine facts shown.
  894 |         await expect(page.locator('#important')).toContainText('Bring your passport');
  895 |         await expect(page.locator('#facts')).toContainText('Moderate');
  896 |         await expect(page.locator('#itinerary')).toContainText('Artisan quarter');
  897 |         await page.screenshot({ path: `../specs/020-tour-detail-experience/evidence/us1/visual/complete-en-${width}.png`, fullPage: true });
  898 |       } finally {
  899 |         await harness.stop();
  900 |       }
  901 |     });
  902 | 
  903 |     test(`US1 SSR sparse tour at ${width}px omits absent sections, entries and review claims`, async ({ page }) => {
  904 |       test.setTimeout(180000);
  905 |       await page.setViewportSize({ width, height: 900 });
  906 |       const slug = 'hidden-gems-rome-walking-tour';
  907 |       const sparse = createSparseTourDetail(new Date('2026-10-14T00:00:00.000Z'));
  908 |       const harness = await startSsrHarness(slug, () => sparse);
  909 |       try {
  910 |         await page.context().addCookies([{ name: 'bookly_cookie_consent', value: 'true', url: `http://127.0.0.1:${harness.nextPort}` }]);
  911 |         const served = await readServedDetail(harness.mockPort, slug, 'en');
  912 |         expect(served.reviews.count).toBe(0);
  913 | 
  914 |         await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
  915 | 
  916 |         await expect(page.getByRole('heading', { level: 1 })).toHaveText(sparse.title);
  917 |         await expect(page.locator('#overview')).toBeVisible();
  918 |         await expect(page.locator('#facts')).toBeVisible();
  919 |         for (const id of ['highlights', 'itinerary', 'included', 'excluded', 'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related']) {
  920 |           await expect(page.locator(`#${id}`)).toHaveCount(0);
```