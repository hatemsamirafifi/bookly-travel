# Research: Bookly UI and AI Tour Translation

## Decision: Preserve the existing API-first stack

**Rationale:** The repository already uses Laravel domain actions/services, PostgreSQL, Redis queues, Next.js App Router, next-intl, Pest, Jest and Playwright. Translation can be additive within these boundaries. Filament remains internal-only.

**Alternatives considered:** Browser-side translation (key exposure and inconsistent SEO), replacing the translation tables (destructive migration), synchronous provider calls on page load/save (latency and failures on critical paths).

## Decision: Gemini REST with backend-only secret

**Rationale:** Google documents `generateContent` as a complete-response REST endpoint suited to non-interactive work and requires `x-goog-api-key`. Laravel HTTP can call it without an extra SDK. Use a configurable stable model. Rotate the key pasted into chat and configure a replacement through a backend secret store.

**Alternatives considered:** Browser SDK (credential exposure), cross-language sidecar (unnecessary operational surface), deprecated `responseSchema` output format (use `responseFormat` instead).

**Sources:** [Gemini API reference](https://ai.google.dev/api), [structured outputs](https://ai.google.dev/gemini-api/docs/generate-content/structured-output), [API key security](https://ai.google.dev/gemini-api/docs/api-key).

**Live REST compatibility note (2026-09-25):** `generationConfig.responseFormat.text.mimeType` must be the enum value `APPLICATION_JSON` in the raw REST request. Sending the SDK-style MIME string `application/json` produced HTTP 400 `INVALID_ARGUMENT`; the corrected value produced a successful synthetic translation through the Laravel adapter.

## Decision: Structured, validated translation payload

**Rationale:** Translate only traveler-facing EN strings (title, description, highlights, inclusions, exclusions, meeting/cancellation text, itinerary day/stop text, and future explicitly reviewed fields). Keep day numbers, durations, guide-language codes, prices, IDs, slugs and availability unchanged. Request structured JSON and validate returned shape, field count and non-linguistic values locally; valid JSON does not prove semantic correctness.

**Alternatives considered:** Free-form text parsing (brittle), sending entire Tour models (private/financial leakage), translating each string separately (excess request volume and inconsistent terminology).

**Source:** [Gemini structured output guidance](https://ai.google.dev/gemini-api/docs/generate-content/structured-output).

## Decision: Revision-safe asynchronous lifecycle

**Rationale:** A per-locale state row records source hash and status without fabricating a row in `tour_translations`. After EN save commits, queue ES/IT. The worker verifies the hash before and after provider I/O and writes only if it still matches. Existing ES/IT records are preserved but not assumed current merely because they exist. A bounded backfill command handles legacy tours.

**Alternatives considered:** Row timestamps (races and ambiguous provenance), deleting old translations before provider success (data loss), requiring ES/IT before publishing (contradicts product decision).

## Decision: Bounded failure handling

**Rationale:** Google documents quotas per project/model and recommends capped exponential backoff with jitter for transient 408/429/5xx, not persistent 400/403. Failed translation keeps current EN fallback and observable status; public APIs never expose provider error details.

**Sources:** [rate limits](https://ai.google.dev/gemini-api/docs/rate-limits), [troubleshooting](https://ai.google.dev/gemini-api/docs/troubleshooting), [safety outcomes](https://ai.google.dev/gemini-api/docs/safety-settings).

## Decision: Minimize provider data exposure

**Rationale:** Send only public-intended tour text, never partner tax/contact data, traveler data, credentials or internal notes. Draft text is not yet public and still leaves Bookly's infrastructure; the owner chose Gemini for this purpose. Data-retention terms vary by service/tier, so zero retention must not be assumed.

**Source:** [Gemini data controls](https://ai.google.dev/gemini-api/docs/zdr).

## Phase 0 baseline: repository and route inventory (2026-09-25)

After `git fetch origin --prune`, `HEAD`, `main`, and `origin/main` all resolve to `f3e8e25`; `origin/main` is an ancestor of the active `018-bookly-ui-redesign` branch and `git branch -r --no-merged origin/main` returns no branches. This verifies the present base, not the older `11c534e` snapshot in the master plan. The feature branch and artifact directory already exist, so switching to `main` and creating another feature branch would risk the current uncommitted work. The worktree has numerous modified and untracked paths, primarily the in-progress tour translation slice; use `git status --short` for the exact current inventory. The backend-only local secret is in ignored `backend/.env`, not in the tracked file list.

The route inventory below is from `frontend/src/app/[locale]`, not a claim that every route has passed browser acceptance. Route groups in parentheses do not appear in URLs.

| Surface | Existing route families | Existing owners / notable behavior |
|---|---|---|
| Public discovery | `/[locale]`, `/search`, `/categories[/[slug]]`, `/destinations[/[slug]]` | Home and search pages use `SearchBar`, `ListingPage`, `FilterPanel`, `SortDropdown`, `TourCard`; public search/detail APIs are in `backend/routes/api/public.php`. |
| Tour detail and booking | `/tours/[slug]`, `/booking`, `/booking/confirmation` | `TourDetail`, `ImageGallery`, `BookingCTA`, `AvailabilityCalendar`, `BookingForm`, and Stripe payment components. Existing checkout has price-change and idempotency handling; redesign must preserve it. |
| Public support | `/blog`, `/blog/[slug]`, `/blog/category/[slug]`, `/blog/[slug]/preview`, `/privacy`, `/terms` | Blog and legal pages retain their current content/preview visibility rules. |
| Authentication | `/auth/login`, `/auth/register`, `/auth/forgot-password`, `/auth/reset-password`, `/auth/verify-email`, `/auth/partner-register`, `/partner-register`, `/partner-invite/[token]` | Auth route-group layout and existing redirect/role behavior. |
| Traveler | `/profile`, `/wishlist`, `/my-bookings[/[reference]]`, `/my-reviews` | Traveler route-group layout plus protected account and review flows. |
| Partner | `/partner`, `/partner/analytics`, `/partner/bookings[/[reference]]`, `/partner/reviews`, `/partner/profile`, `/partner/onboarding`, `/partner/tours`, `/partner/tours/create`, `/partner/tours/[id]/edit`, `/availability`, `/pricing` | Partner layout and API ownership guard in `backend/routes/api/partner.php`. |
| Recovery/admin | Locale `not-found` and catch-all pages; root `/v/[reference]` voucher verification; internal `/admin` Filament resources | Voucher QR route is deliberately outside locale prefix and marked `noindex`; `backend/app/Filament/Resources` contains Tour, Booking, Partner, Review, Blog, Static Page, and audit resources. |

Existing reusable patterns include `frontend/src/components/ui` buttons, inputs, select, feedback states and rating; shared `Header`, `Footer`, `MobileNavPanel`; search cards/filter components; and tour/booking components. Existing Playwright suites cover each major family, including Axe suites under `frontend/tests/e2e/a11y`, but coverage presence is not evidence of redesign acceptance. The current CSS and TypeScript token definitions use Bookly navy/gold but are not fully aligned: `globals.css` still maps `--font-sans` to Inter, `design-tokens.ts` names Inter, while many components use literal color classes. `TourDetail` is a large page component with limited section navigation and a simple gallery; the current header has one primary row, not the proposed two-level discovery hierarchy. The partner itinerary editor and tour media ordering remain separate implementation work. These are observed code-state findings, not completed Phase 1–3 features.

## Phase 0 reference hierarchy and Bookly design boundary

The [Tripadvisor homepage](https://www.tripadvisor.com/) currently leads with a prominent search prompt, interest categories, experience cards and editorial inspiration. The [specific Nile cruise reference](https://www.tripadvisor.com/AttractionProductReview-g294204-d14111748-4_Day_3_Night_Nile_Cruise_from_Aswan_to_Luxor_Private_Tour-Aswan_Aswan_Governorate.html) is indexed with a tour title, review evidence, imagery and an overview; an [indexed section of that listing](https://www.tripadvisor.com/AttractionProductReview-g294204-d14111748-or50-4_Day_3_Night_Nile_Cruise_from_Aswan_to_Luxor_Private_Tour-Aswan_Aswan_Govern.html) shows highlights and included/excluded content. Direct fetching of the specific detail page failed, and no reliable mobile screenshot was available in this review. Therefore the following is a Bookly design proposal inspired by the confirmed content hierarchy, not a claim of pixel-for-pixel observation of Tripadvisor's desktop or mobile UI.

| Surface | Desktop hierarchy proposal | Narrow-screen hierarchy proposal | Bookly differentiation / guard |
|---|---|---|---|
| Home | Bookly header → prominent tours-only search → categories/destinations → data-backed featured tours → editorial content → partner invitation | Compact header and search first; horizontally scrollable discovery rails with visible labels | Navy/gold identity; no hotels, restaurant, rewards or ad modules. |
| Search | Query/context above results; filter column, sort control, cards with real price/review evidence | Query/context retained; accessible filter drawer and sort; one-column cards | URL-backed criteria and browser history; empty/error recovery. |
| Tour detail | Title/review/location → gallery → section navigation → content and right booking card | Title → swipeable gallery → facts/content → persistent but non-obscuring booking action | Optional sections omitted; live-guide codes separate from EN/ES/IT content; no invented offers. |
| Checkout | Authoritative tour/date/traveler summary beside payment and recovery states | Summary before payment; clear step/total and changed-price acknowledgment | Preserve Laravel/Stripe idempotency and server totals. |

Gallery behavior: use real ordered tour media, selected cover first, captions when provided, keyboard-operable lightbox with Escape/focus restoration, and a stable mobile aspect ratio. Section navigation must link only rendered sections and account for the sticky header. Filters must preserve query/locale on apply, clear, back and forward. A desktop sticky booking card needs a mobile equivalent that never hides form controls or implies availability the API has not confirmed.

Accessibility risks are focus trapping/restoration in galleries and drawers, sticky elements covering anchored headings, unlabeled icon controls, low-contrast muted text, reduced-motion omissions and touch targets below the intended size. Performance risks are oversized gallery images, many client components, hydration-heavy filter state and unmeasured third-party scripts. Require production-build Lighthouse evidence, image sizing, browser keyboard review and Axe checks before declaring a route done.

Do not copy Tripadvisor trademarks, logo, source code, text, photos, review values, award badges, advertising, or unsupported feature claims. The reference is limited to information hierarchy and interaction patterns; every Bookly claim must originate in Bookly data and contracts.
