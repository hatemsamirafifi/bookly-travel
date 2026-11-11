# Search

<!-- EGM contract -->

## Overview

Presents catalog results and reusable tour cards, with query-backed filters, sorting, pagination and responsive filter controls.

## Interface

- [ActiveFilterChips.tsx](./ActiveFilterChips.tsx#L11) declares `default ActiveFilterChips`.
- [FilterPanel.tsx](./FilterPanel.tsx#L52) declares `default FilterPanel`.
- [FilterSidebar.tsx](./FilterSidebar.tsx#L51) declares `default FilterSidebar`.
- [ListingPage.tsx](./ListingPage.tsx#L20) declares `default ListingPage`.
- [MobileFilterDrawer.tsx](./MobileFilterDrawer.tsx#L18) declares `default MobileFilterDrawer`.
- [Pagination.tsx](./Pagination.tsx#L12) declares `default Pagination`.
- [SearchBar.tsx](./SearchBar.tsx#L12) declares `default SearchBar`.
- [SearchResults.tsx](./SearchResults.tsx#L51) declares `default SearchResults`.
- [SearchUnavailable.tsx](./SearchUnavailable.tsx#L14) declares `default SearchUnavailable`.
- [SortDropdown.tsx](./SortDropdown.tsx#L6) declares `default SortDropdown`.
- [TourCard.tsx](./TourCard.tsx#L27) declares `default TourCard`.

## Invariants

- Search submission trims `q`, preserves other search parameters and removes `page`. The mobile drawer copies active URL filters on open and stages changes until Apply, which includes explicit clears. [SearchBar.tsx](./SearchBar.tsx#L25) [MobileFilterDrawer.tsx](./MobileFilterDrawer.tsx#L29) [MobileFilterDrawer.tsx](./MobileFilterDrawer.tsx#L63)
- The responsive filter panels derive the date input minimum after hydration, with an empty server snapshot. [FilterPanel.tsx](./FilterPanel.tsx#L78) [MobileFilterDrawer.tsx](./MobileFilterDrawer.tsx#L14)

## Side Effects

- Search and pagination navigate by updating the query string. Mobile filter UI installs an Escape listener while open and returns focus to the trigger on close. [SearchBar.tsx](./SearchBar.tsx#L34) [Pagination.tsx](./Pagination.tsx#L20) [MobileFilterDrawer.tsx](./MobileFilterDrawer.tsx#L35)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
