# Home

<!-- EGM contract -->

## Overview

Composes home-page discovery sections from supplied tours, categories, destinations and articles, with section-level recovery UI.

## Interface

- [BlogInspiration.tsx](./BlogInspiration.tsx#L11) declares `default BlogInspiration`.
- [BooklyValuePanel.tsx](./BooklyValuePanel.tsx#L16) declares `default BooklyValuePanel`.
- [CatalogRecoveryAlert.tsx](./CatalogRecoveryAlert.tsx#L10) declares `default CatalogRecoveryAlert`.
- [CategoryChips.tsx](./CategoryChips.tsx#L9) declares `default CategoryChips`.
- [CategoryGrid.tsx](./CategoryGrid.tsx#L12) declares `default CategoryGrid`.
- [DestinationShowcase.tsx](./DestinationShowcase.tsx#L12) declares `default DestinationShowcase`.
- [FeaturedTours.tsx](./FeaturedTours.tsx#L12) declares `default FeaturedTours`.
- [HeroSection.tsx](./HeroSection.tsx#L8) declares `default HeroSection`.
- [HomeErrorBoundary.tsx](./HomeErrorBoundary.tsx#L16) declares `default HomeErrorBoundary`.
- [PartnerAcquisitionPanel.tsx](./PartnerAcquisitionPanel.tsx#L8) declares `default PartnerAcquisitionPanel`.
- [ThemedTourRails.tsx](./ThemedTourRails.tsx#L9) declares `default ThemedTourRails`.

## Invariants

- Themed rails reuse `FeaturedTours` and render only rails with a nonempty tour array. Empty article, category or destination data omits the corresponding section. [ThemedTourRails.tsx](./ThemedTourRails.tsx#L9) [BlogInspiration.tsx](./BlogInspiration.tsx#L11) [CategoryGrid.tsx](./CategoryGrid.tsx#L12) [DestinationShowcase.tsx](./DestinationShowcase.tsx#L12)

## Side Effects

- The catalog recovery alert calls the supplied retry action or reloads the page. The section error boundary logs render failures in development and shows the provided fallback. [CatalogRecoveryAlert.tsx](./CatalogRecoveryAlert.tsx#L13) [HomeErrorBoundary.tsx](./HomeErrorBoundary.tsx#L26)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
