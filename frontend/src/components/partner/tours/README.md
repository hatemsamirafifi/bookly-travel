# Tours

<!-- EGM contract -->

## Overview

Provides partner tour authoring, draft and review submission, availability and pricing forms, media upload and content preview.

## Interface

- [AvailabilityCalendar.tsx](./AvailabilityCalendar.tsx#L25) declares `AvailabilityCalendar`.
- [BookingOptionsForm.tsx](./BookingOptionsForm.tsx#L36) declares `BookingOptionsForm`.
- [ImageUploader.tsx](./ImageUploader.tsx#L32) declares `ImageUploader`.
- [index.ts](./index.ts#L1) declares `TourCard`, `TourList`, `TourWizard`, `ImageUploader`, `PricingTierForm`, `BookingOptionsForm`, `AvailabilityCalendar`, `PartnerRichTextEditor`.
- [ItineraryEditor.tsx](./ItineraryEditor.tsx#L33) declares `ItineraryEditor`.
- [PartnerRichTextEditor.tsx](./PartnerRichTextEditor.tsx#L5) declares `PartnerRichTextEditorProps`, `PartnerRichTextEditor`, `default export`.
- [PricingTierForm.tsx](./PricingTierForm.tsx#L13) declares `PricingTierForm`.
- [TourCard.tsx](./TourCard.tsx#L36) declares `TourCard`.
- [TourContentPreview.tsx](./TourContentPreview.tsx#L31) declares `TourContentPreview`.
- [TourList.tsx](./TourList.tsx#L13) declares `TourList`.
- [TourWizard.tsx](./TourWizard.tsx#L59) declares `TourWizard`.

## Invariants

- The wizard sends the writing language on creation and retains the created ID for subsequent updates. It adopts stored option IDs and sends the full options array on update so removed options can retire. [TourWizard.tsx](./TourWizard.tsx#L288)
- Draft save requires a writing language and valid image alt drafts. Review submission also validates the wizard steps and source text, then submits the materialized draft by ID. [TourWizard.tsx](./TourWizard.tsx#L311) [TourWizard.tsx](./TourWizard.tsx#L341)
- The uploader enforces count, type and file-size limits before upload and keeps over-length alt drafts local. The content preview sanitizes rich text before injecting HTML. [ImageUploader.tsx](./ImageUploader.tsx#L38) [ImageUploader.tsx](./ImageUploader.tsx#L115) [TourContentPreview.tsx](./TourContentPreview.tsx#L64)

## Side Effects

- Creates or updates the draft through the partner API, calls `submitTour` for review, then resets wizard state and navigates on success. [TourWizard.tsx](./TourWizard.tsx#L295) [TourWizard.tsx](./TourWizard.tsx#L375)
- The uploader requests a signed URL and PUTs the file to storage before emitting a media entry. The rich-text editor loads the bundled HugeRTE script and fetches the partner media picker list. [ImageUploader.tsx](./ImageUploader.tsx#L61) [PartnerRichTextEditor.tsx](./PartnerRichTextEditor.tsx#L64) [PartnerRichTextEditor.tsx](./PartnerRichTextEditor.tsx#L93)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
