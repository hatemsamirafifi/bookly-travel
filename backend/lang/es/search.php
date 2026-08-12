<?php

// Spec 006 — superficie pública de búsqueda y descubrimiento. Mensajes 429
// de límite de frecuencia, localizados según category-destination-api.md:174
// y search-api.md:124.
return [
    'rate_limit' => [
        'search' => 'Demasiadas solicitudes de búsqueda. Espere e inténtelo de nuevo en breve.',
        'default' => 'Demasiadas solicitudes. Espere e inténtelo de nuevo en breve.',
    ],
];
