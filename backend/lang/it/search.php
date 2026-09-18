<?php

// Spec 006 — superficie pubblica di ricerca e scoperta. Messaggi di limite
// richieste (429) localizzati secondo category-destination-api.md:174 e
// search-api.md:124. Etichette di durata secondo search-api.md:146.
return [
    'rate_limit' => [
        'search' => 'Troppe richieste di ricerca. Attendere e riprovare a breve.',
        'default' => 'Troppe richieste. Attendere e riprovare a breve.',
    ],

    'durations' => [
        'half_day' => 'Mezza giornata (≤4h)',
        'full_day' => 'Giornata intera (4-8h)',
        'multi_day' => 'Più giorni (>8h)',
    ],
];
