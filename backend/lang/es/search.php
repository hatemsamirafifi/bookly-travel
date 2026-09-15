<?php

// Spec 006 — superficie pública de búsqueda y descubrimiento. Mensajes de
// límite de peticiones (429) localizados según category-destination-api.md:174
// y search-api.md:124. Etiquetas de duración según search-api.md:146.
return [
    'rate_limit' => [
        'search' => 'Demasiadas solicitudes de búsqueda. Espere e inténtelo de nuevo en breve.',
        'default' => 'Demasiadas solicitudes. Espere e inténtelo de nuevo en breve.',
    ],

    'durations' => [
        'half_day' => 'Medio día (≤4h)',
        'full_day' => 'Día completo (4-8h)',
        'multi_day' => 'Varios días (>8h)',
    ],
];
