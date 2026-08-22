<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Profanity Filter Provisioning (Spec 009, FR-014)
|--------------------------------------------------------------------------
|
| The profanity filter MUST work in every environment without manual setup.
|
| `default_path` points to a tracked, version-controlled baseline keyword
| list shipped with the repo (resources/profanity_keywords.json). This is the
| source of truth in fresh clones, CI, and production.
|
| `override_path` is an optional, gitignored file (storage/app/) that, when
| present, FULLY REPLACES the default list — letting an environment swap the
| keyword set without a code deploy. An absent/empty override falls back to
| the default. If both resolve to no keywords, the filter degrades to a no-op
| and logs a warning (see ProfanityFilterService) so the misconfiguration is
| observable rather than silent.
|
| Semantics: override REPLACES (not merges) the default. An override file is
| expected to contain the complete desired keyword set.
*/
return [
    'default_path' => resource_path('profanity_keywords.json'),
    'override_path' => storage_path('app/profanity_keywords.json'),
];
