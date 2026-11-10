# Ui

<!-- EGM contract -->

## Overview

Provides reusable form controls, overlays, navigation, feedback and layout primitives used by feature components.

## Interface

- [Accordion.tsx](./Accordion.tsx#L6) declares `Accordion`.
- [Breadcrumbs.tsx](./Breadcrumbs.tsx#L3) declares `Breadcrumbs`.
- [button.tsx](./button.tsx#L4) declares `ButtonProps`, `Button`.
- [CarouselRail.tsx](./CarouselRail.tsx#L4) declares `CarouselRail`.
- [Chip.tsx](./Chip.tsx#L6) declares `Chip`.
- [Container.tsx](./Container.tsx#L4) declares `Container`.
- [Dialog.tsx](./Dialog.tsx#L40) declares `DialogProps`, `Dialog`.
- [Drawer.tsx](./Drawer.tsx#L5) declares `Drawer`.
- [EmptyState.tsx](./EmptyState.tsx#L14) declares `default EmptyState`.
- [ErrorState.tsx](./ErrorState.tsx#L12) declares `default ErrorState`.
- [IconButton.tsx](./IconButton.tsx#L4) declares `IconButton`.
- [ImageWithFallback.tsx](./ImageWithFallback.tsx#L11) declares `default ImageWithFallback`.
- [input.tsx](./input.tsx#L4) declares `InputProps`, `Input`.
- [label.tsx](./label.tsx#L4) declares `LabelProps`, `Label`.
- [LoadingSkeleton.tsx](./LoadingSkeleton.tsx#L56) declares `default LoadingSkeleton`.
- [RatingBadge.tsx](./RatingBadge.tsx#L3) declares `RatingBadge`.
- [SectionHeading.tsx](./SectionHeading.tsx#L3) declares `SectionHeading`.
- [select.tsx](./select.tsx#L118) declares `Select`, `SelectTrigger`, `SelectContent`, `SelectItem`, `SelectValue`.
- [StarRating.tsx](./StarRating.tsx#L20) declares `Star`, `default StarRating`.
- [switch.tsx](./switch.tsx#L37) declares `Switch`.
- [Tabs.tsx](./Tabs.tsx#L5) declares `Tabs`.
- [textarea.tsx](./textarea.tsx#L4) declares `TextareaProps`, `Textarea`.
- [Toast.tsx](./Toast.tsx#L5) declares `ToastType`, `default Toast`, `ToastContainer`.

## Invariants

- Only the top dialog handles Escape and Tab. Shared body locking and inert state persist across stacked dialogs and restore when the last dialog closes. Drawer delegates to the same dialog. [Dialog.tsx](./Dialog.tsx#L60) [Dialog.tsx](./Dialog.tsx#L86) [Drawer.tsx](./Drawer.tsx#L5)
- Compound select parts use a shared `Select` context. `RatingBadge` renders only for a finite rating above zero and at most five with a positive integer review count. [select.tsx](./select.tsx#L19) [RatingBadge.tsx](./RatingBadge.tsx#L3)

## Side Effects

- Dialog moves focus into the top overlay and restores focus on cleanup. It sets body overflow, toggles inert on background siblings and installs a keydown listener. [Dialog.tsx](./Dialog.tsx#L60) [Dialog.tsx](./Dialog.tsx#L86)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
