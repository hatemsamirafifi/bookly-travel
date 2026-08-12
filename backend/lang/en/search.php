<?php

// Spec 006 — public search & discovery surface. Rate-limit 429 messages,
// localized per category-destination-api.md:174 and search-api.md:124.
return [
    'rate_limit' => [
        'search' => 'Too many search requests. Please wait and try again shortly.',
        'default' => 'Too many requests. Please wait and try again shortly.',
    ],
];
