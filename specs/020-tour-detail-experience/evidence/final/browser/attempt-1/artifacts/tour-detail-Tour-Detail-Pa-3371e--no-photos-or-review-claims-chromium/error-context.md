# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tour-detail.spec.ts >> Tour Detail Page >> controlled legacy-shaped empty and adopted-day SSR responses invent no photos or review claims
- Location: tests/e2e/tour-detail.spec.ts:30:7

# Error details

```
Error: expect(locator).toContainText(expected) failed

Locator: locator('#itinerary')
Expected substring: "Legacy arrival"
Error: strict mode violation: locator('#itinerary') resolved to 2 elements:
    1) <section id="itinerary" class="scroll-mt-20">…</section> aka locator('#main-content').getByText('ItineraryDay 1: Legacy')
    2) <section id="itinerary" class="scroll-mt-20">…</section> aka getByText('ItineraryDay 1: Legacy').nth(1)

Call log:
  - Expect "toContainText" with timeout 20000ms
  - waiting for locator('#itinerary')

```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - banner [ref=e2]:
    - link "Skip to main content" [ref=e3] [cursor=pointer]:
      - /url: "#main-content"
    - generic [ref=e4]:
      - link "Bookly" [ref=e5] [cursor=pointer]:
        - /url: /en
      - navigation "Main navigation" [ref=e6]:
        - link "Home" [ref=e7] [cursor=pointer]:
          - /url: /en
        - link "Search tours" [ref=e8] [cursor=pointer]:
          - /url: /en/search
        - link "Categories" [ref=e9] [cursor=pointer]:
          - /url: /en/categories
        - link "Destinations" [ref=e10] [cursor=pointer]:
          - /url: /en/destinations
      - generic [ref=e11]:
        - button "Search tours" [ref=e12]:
          - img [ref=e13]
        - combobox "Switch language" [ref=e18]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
        - link "Sign In" [ref=e19] [cursor=pointer]:
          - /url: /en/auth/login
          - generic [ref=e20]: Sign In
        - link "Sign Up" [ref=e21] [cursor=pointer]:
          - /url: /en/auth/register
  - main [ref=e22]:
    - generic [ref=e23]:
      - generic [ref=e24]:
        - navigation "Breadcrumbs" [ref=e25]:
          - list [ref=e26]:
            - listitem [ref=e27]:
              - link "Home" [ref=e28] [cursor=pointer]:
                - /url: /en
            - listitem [ref=e29]:
              - generic [ref=e30]: /
              - link "Walking Tours" [ref=e31] [cursor=pointer]:
                - /url: /en/categories/walking
            - listitem [ref=e32]:
              - generic [ref=e33]: /
              - generic [ref=e34]: Hidden Gems of Rome Walking Tour
        - generic [ref=e35]:
          - heading "Hidden Gems of Rome Walking Tour" [level=1] [ref=e36]
          - button "Save to wishlist" [ref=e37]:
            - generic [ref=e38]: ♡
        - generic [ref=e40]:
          - img [ref=e41]
          - text: Rome, Italy
      - status [ref=e45]: No photos are available for this tour.
      - navigation "Tour sections" [ref=e46]:
        - generic [ref=e47]:
          - link "About This Tour" [ref=e48] [cursor=pointer]:
            - /url: "#overview"
          - link "Tour facts" [ref=e49] [cursor=pointer]:
            - /url: "#facts"
          - link "Highlights" [ref=e50] [cursor=pointer]:
            - /url: "#highlights"
          - link "Itinerary" [ref=e51] [cursor=pointer]:
            - /url: "#itinerary"
          - link "What's Included" [ref=e52] [cursor=pointer]:
            - /url: "#included"
          - link "What's Excluded" [ref=e53] [cursor=pointer]:
            - /url: "#excluded"
          - link "Cancellation Policy" [ref=e54] [cursor=pointer]:
            - /url: "#cancellation"
      - generic [ref=e55]:
        - article [ref=e56]:
          - generic [ref=e57]:
            - heading "About This Tour" [level=2] [ref=e58]
            - generic [ref=e59]: Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.
          - generic [ref=e60]:
            - heading "Tour facts" [level=2] [ref=e61]
            - generic [ref=e62]:
              - generic [ref=e63]:
                - term [ref=e64]: Duration
                - definition [ref=e65]: 3 hours
              - generic [ref=e66]:
                - term [ref=e67]: Group size
                - definition [ref=e68]: 1–20 travelers
          - generic [ref=e69]:
            - heading "Highlights" [level=2] [ref=e70]
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
            - heading "Itinerary" [level=2] [ref=e85]
            - list [ref=e86]:
              - listitem [ref=e87]:
                - 'heading "Day 1: Legacy arrival" [level=3] [ref=e88]'
              - listitem [ref=e89]:
                - 'heading "Day 2: Legacy walking route" [level=3] [ref=e90]'
          - generic [ref=e91]:
            - heading "What's Included" [level=3] [ref=e95]:
              - button "What's Included" [ref=e96]:
                - text: What's Included
                - img [ref=e97]
            - heading "What's Excluded" [level=3] [ref=e102]:
              - button "What's Excluded" [ref=e103]:
                - text: What's Excluded
                - img [ref=e104]
          - generic [ref=e106]:
            - heading "Cancellation Policy" [level=2] [ref=e107]
            - paragraph [ref=e108]: Free cancellation up to 24 hours before the tour.
        - complementary [ref=e109]:
          - generic [ref=e111]:
            - generic [ref=e113]:
              - text: €45.00
              - generic [ref=e114]: / person
            - generic [ref=e115]:
              - generic [ref=e116]: Select a Date
              - generic [ref=e117]:
                - paragraph [ref=e118]: Next available
                - paragraph [ref=e119]: 10/17/2026
              - combobox "Select a Date" [ref=e120]:
                - option "Select a Date"
                - option "10/17/2026" [selected]
                - option "10/18/2026"
                - option "10/19/2026"
                - option "10/20/2026"
                - option "10/21/2026"
                - option "10/22/2026"
                - option "10/23/2026"
                - option "10/24/2026"
                - option "10/25/2026"
                - option "10/26/2026"
                - option "10/27/2026"
                - option "10/28/2026"
                - option "10/29/2026"
                - option "10/30/2026"
                - option "10/31/2026"
                - option "11/01/2026"
                - option "11/02/2026"
                - option "11/03/2026"
                - option "11/04/2026"
                - option "11/05/2026"
                - option "11/06/2026"
                - option "11/07/2026"
                - option "11/08/2026"
                - option "11/09/2026"
                - option "11/10/2026"
                - option "11/11/2026"
                - option "11/12/2026"
                - option "11/13/2026"
                - option "11/14/2026"
                - option "11/15/2026"
                - option "11/16/2026"
                - option "11/17/2026"
                - option "11/18/2026"
                - option "11/19/2026"
                - option "11/20/2026"
                - option "11/21/2026"
                - option "11/22/2026"
                - option "11/23/2026"
                - option "11/24/2026"
                - option "11/25/2026"
                - option "11/26/2026"
                - option "11/27/2026"
                - option "11/28/2026"
                - option "11/29/2026"
                - option "11/30/2026"
                - option "12/01/2026"
                - option "12/02/2026"
                - option "12/03/2026"
                - option "12/04/2026"
                - option "12/05/2026"
                - option "12/06/2026"
                - option "12/07/2026"
                - option "12/08/2026"
                - option "12/09/2026"
                - option "12/10/2026"
                - option "12/11/2026"
                - option "12/12/2026"
                - option "12/13/2026"
                - option "12/14/2026"
            - generic [ref=e121]:
              - generic [ref=e122]: Participants
              - group "Participants" [ref=e123]:
                - button "Decrease participants" [disabled] [ref=e124]: "-"
                - status [ref=e125]: "1"
                - button "Increase participants" [ref=e126]: +
                - generic [ref=e127]: 1–20 allowed
            - paragraph [ref=e128]: "Estimated total: €45.00"
            - paragraph [ref=e129]: Free cancellation up to 24 hours before the tour.
            - generic [ref=e130]:
              - paragraph [ref=e131]: Sign in to continue booking. Your tour, date, traveler count and language are kept, and nothing is booked until you confirm.
              - link "Book Now" [ref=e132] [cursor=pointer]:
                - /url: /en/auth/login?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
              - link "Create account" [ref=e133] [cursor=pointer]:
                - /url: /en/auth/register?returnUrl=%2Fen%2Fbooking%3Ftour%3Dhidden-gems-rome-walking-tour%26participants%3D1%26date%3D2026-10-17
  - contentinfo [ref=e134]:
    - generic [ref=e135]:
      - generic [ref=e136]:
        - img "Bookly Travel — Book smarter. Travel better." [ref=e139]
        - generic [ref=e140]:
          - paragraph [ref=e141]: Explore
          - list [ref=e142]:
            - listitem [ref=e143]:
              - link "Search tours" [ref=e144] [cursor=pointer]:
                - /url: /en/search
            - listitem [ref=e145]:
              - link "Categories" [ref=e146] [cursor=pointer]:
                - /url: /en/categories
            - listitem [ref=e147]:
              - link "Destinations" [ref=e148] [cursor=pointer]:
                - /url: /en/destinations
        - generic [ref=e149]:
          - paragraph [ref=e150]: Company
          - list [ref=e151]:
            - listitem [ref=e152]:
              - link "Privacy policy" [ref=e153] [cursor=pointer]:
                - /url: /en/privacy
            - listitem [ref=e154]:
              - link "Terms of service" [ref=e155] [cursor=pointer]:
                - /url: /en/terms
      - generic [ref=e156]:
        - generic [ref=e157]: © 2026 Bookly. All rights reserved.
        - combobox "Switch language" [ref=e159]:
          - option "English" [selected]
          - option "Español"
          - option "Italiano"
  - complementary "Cookie consent":
    - generic [ref=e160]:
      - generic [ref=e161]: We use cookies to enhance your browsing experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies.
      - generic [ref=e162]:
        - button "Reject" [ref=e163]
        - button "Accept" [ref=e164]
  - alert [ref=e165]
```

# Test source

```ts
  1   | import { test, expect, type Page } from '@playwright/test';
  2   | import type { EnglishSourceInput, TourDetail } from '@/lib/api/types';
  3   | import {
  4   |   apiGet,
  5   |   apiPut,
  6   |   ownedCoverUrl,
  7   |   ownedDifficulty,
  8   |   ownedEnglishSource,
  9   |   ownedGuideLanguages,
  10  |   ownedMedia,
  11  | } from './partner/tour-content-helpers';
  12  | import { fetchServedResponse, readServedDetail, startSsrHarness, enableNativeMediaProxy } from './helpers/tour-detail-ssr';
  13  | import type { SsrReading } from './helpers/tour-detail-ssr';
  14  | import { formatCurrency } from '@/lib/utils';
  15  | import {
  16  |   createFullTourDetail,
  17  |   createSparseTourDetail,
  18  |   createUnavailableTourDetail,
  19  | } from '../fixtures/tour-detail';
  20  | 
  21  | test.beforeEach(async ({ page }) => {
  22  |   await enableNativeMediaProxy(page.context());
  23  | });
  24  | 
  25  | test.afterEach(async ({ context }) => {
  26  |   await context.unrouteAll({ behavior: 'wait' });
  27  | });
  28  | 
  29  | test.describe('Tour Detail Page', () => {
  30  |   test('controlled legacy-shaped empty and adopted-day SSR responses invent no photos or review claims', async ({ page, request }) => {
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
> 65  |       await expect(page.locator('#itinerary')).toContainText('Legacy arrival');
      |                                                ^ Error: expect(locator).toContainText(expected) failed
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
  131 |         await expect(page.locator('main#main-content').getByText(scenario.notice)).toBeVisible();
  132 |         await expect(page.locator('main#main-content').getByText(scenario.itineraryNotice)).toBeVisible();
  133 |         await expect(fallbackHeading).toHaveAttribute('lang', 'en');
  134 |         await expect(page.locator('main#main-content #itinerary')).toContainText('English arrival');
  135 |         await expect(page.locator('main#main-content #itinerary h3 span').first()).toHaveAttribute('lang', 'en');
  136 |         await expect(page.locator('main#main-content').getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
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
  158 |         await expect(page.locator('main#main-content').getByText(scenario.notice)).toHaveCount(0);
  159 |         await expect(page.locator('main#main-content').getByText(scenario.itineraryNotice)).toHaveCount(0);
  160 |         await expect(readyHeading).toHaveAttribute('lang', scenario.locale);
  161 |         await expect(page.locator('main#main-content #itinerary')).toContainText(scenario.day);
  162 |         await expect(page.locator('main#main-content #itinerary')).not.toContainText('English arrival');
  163 |         await expect(page.locator('main#main-content #itinerary h3 span').first()).toHaveAttribute('lang', scenario.locale);
  164 |         await expect(page.locator('main#main-content').getByText(new RegExp(`${scenario.guide}:`))).toBeVisible();
  165 |         const schema = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '{}');
```