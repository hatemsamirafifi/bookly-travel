<?php

// Spec 006 — superficie pubblica di ricerca e scoperta. Messaggi 429 di
// limite di frequenza, localizzati secondo category-destination-api.md:174
// e search-api.md:124.
return [
    'rate_limit' => [
        'search' => 'Troppe richieste di ricerca. Attendere e riprovare a breve.',
        'default' => 'Troppe richieste. Attendere e riprovare a breve.',
    ],
];
