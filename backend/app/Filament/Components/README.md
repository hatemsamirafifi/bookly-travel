# Components

<!-- EGM contract -->

## Overview

Reusable Filament rich-text editor and media selection field.

## Interface

- [HugeRteEditor.php](./HugeRteEditor.php#L14) declares `HugeRteEditor`, `HugeRteEditor::toolbarProfile()`, `HugeRteEditor::getToolbarProfile()`, `HugeRteEditor::getToolbarButtons()`.
- [MediaPicker.php](./MediaPicker.php#L18) declares `MediaPicker`, `MediaPicker::storesAttribute()`, `MediaPicker::make()`.

## Invariants

- `HugeRteEditor::setUp` sanitizes editor HTML on dehydration through `HtmlSanitizerService`. [HugeRteEditor.php](./HugeRteEditor.php#L65)
- `MediaPicker::searchOptions` only offers ready, non-deleted assets whose backing files exist and uses the configured storage attribute as the option value. [MediaPicker.php](./MediaPicker.php#L60)

## Side Effects

- `MediaPicker` queries media assets and checks their backing files when populating results. [MediaPicker.php](./MediaPicker.php#L46)

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
