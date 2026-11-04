# UI Contract: Homepage & Discovery Surfaces (Spec 021)

**Status**: Active UI Specification  
**Design Tokens**: Bookly Design System (Tailwind CSS 4, WCAG 2.1 AA)

---

## 1. Homepage 10-Section Vertical Layout Matrix

| # | Section Name | Component Path | SSR / Client | Behavior on Missing / Errored Data |
|:-:|:-------------|:---------------|:-------------|:-----------------------------------|
| 1 | “Where to?” Search Hero | `frontend/src/components/home/HeroSection.tsx` | Server + Client SearchBar | Always rendered; provides fallback search if core catalog fails. |
| 2 | Tour-Category Chips | `frontend/src/components/home/CategoryChips.tsx` | Server Component | Rendered if categories exist; clicking navigates to `/search?category=<slug>`. |
| 3 | Bookly Value Panel | `frontend/src/components/home/BooklyValuePanel.tsx` | Server Component | Static brand trust panel; zero external ads. |
| 4 | Popular Categories | `frontend/src/components/home/CategoryGrid.tsx` | Server Component | Rendered if `popular_categories.length > 0`; otherwise hidden. |
| 5 | Featured Tours Rail | `frontend/src/components/home/FeaturedTours.tsx` | Server Component | Horizontal snap-scroll rail; renders if `featured_tours.length > 0`. |
| 6 | Top Destinations | `frontend/src/components/home/DestinationShowcase.tsx` | Server Component | Grid of 3–6 top destinations; hidden if empty. |
| 7 | Themed Tour Rails | `frontend/src/components/home/ThemedTourRails.tsx` | Server Component | Dynamically renders each non-empty `curated_rails` item; hides empty rails. |
| 8 | Blog Inspiration | `frontend/src/components/home/BlogInspiration.tsx` | Server Component | Rendered if `articles.length > 0`; omitted cleanly on API failure. |
| 9 | Partner Acquisition CTA | `frontend/src/components/home/PartnerAcquisitionPanel.tsx` | Server Component | Static secondary CTA linking to `/partner-register`. |
| 10 | Global Application Footer | `frontend/src/components/shared/Footer.tsx` | Server Component | Standard global footer with locale switcher. |

---

## 2. Standardized `TourCard` Specification

### Geometry & Layout
- **Container**: Rounded corners (`rounded-xl`), subtle border (`border border-border`), surface background (`bg-surface`).
- **Cover Image**: Aspect ratio `4:3` (`aspect-[4/3]`) with `object-cover`.
- **LCP Optimization**: Prop `imagePriority?: boolean`. When `true`, rendered with Next.js Image `priority={true}` (`fetchpriority="high"`, `loading="eager"`). Only the first visible card in the primary rail receives `true`.

### Interactive Elements & a11y
1. **Primary Link**: Wraps the image, title, and metadata pointing to `/[locale]/tours/[slug]`.
   - Focus ring: `focus-visible:outline-2 focus-visible:outline-focus focus-visible:outline-offset-2`.
2. **Wishlist Action**: Standalone `<button type="button">` absolutely positioned in the top-right corner.
   - Touch target: $\ge 44\times44\text{px}$ bounding box.
   - States: Inactive (outlined heart, gray), Active (solid red/crimson heart).
   - Accessibility: `aria-label="Save [Tour Title] to wishlist"` / `"Remove [Tour Title] from wishlist"`. Screen-reader live region notification upon toggle.

---

## 3. Responsive Search & Filter Interface Contract

### Desktop Layout ($\ge 1024\text{px}$)
- **Left Column (Width: 280px)**: Sticky `FilterSidebar` component.
  - Price Range: Sliders / inputs with min/max bounds.
  - Categories: Multi-select checkbox group with count indicators.
  - Rating: Star rating selector ($\ge 4.5, \ge 4.0, \ge 3.5$).
  - Duration: Bucketed checkboxes (`< 2h`, `2–4h`, `4–8h`, `Full day`).
- **Right Column (Flex-1)**:
  - Header: Total results summary (e.g., *"Showing 42 tours in Rome"*) + Sort dropdown.
  - Active Filters: Dismissible chips bar (`ActiveFilterChips`).
  - Results Grid: Dense 3-column grid (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6`).

### Mobile Layout ($< 768\text{px}$)
- **Sticky Trigger Bar**: Floating / sticky bar at top or bottom with `Filters (N)` and `Sort` buttons (minimum $44\times44\text{px}$).
- **`MobileFilterDrawer`**:
  - Full-height slide-over modal dialog (`role="dialog"`, `aria-modal="true"`).
  - Background backdrop with click-to-dismiss and Escape key listener.
  - Scrollable content area containing all filter sections with touch targets $\ge 44\times44\text{px}$.
  - **Sticky Bottom Action Bar**:
    - "Reset" button (clears staged selections).
    - "Show [N] Tours" primary button (commits staged filters to URL and closes drawer).

