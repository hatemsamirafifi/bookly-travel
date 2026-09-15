<?php

// Spec 006 — public search & discovery surface. Rate-limit 429 messages,
// localized per category-destination-api.md:174 and search-api.md:124.
// Duration filter labels localized per search-api.md:99-103/146 (the
// `filters.durations[].label` contract).
return [
    'rate_limit' => [
        'search' => 'Too many search requests. Please wait and try again shortly.',
        'default' => 'Too many requests. Please wait and try again shortly.',
    ],

    'durations' => [
        'half_day' => 'Half Day (≤4h)',
        'full_day' => 'Full Day (4-8h)',
        'multi_day' => 'Multi Day (>8h)',
    ],
];
