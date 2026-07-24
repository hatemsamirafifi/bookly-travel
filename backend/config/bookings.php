<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Tour Start Time
    |--------------------------------------------------------------------------
    | Fallback tour start time (H:i:s) used when a booking has no snapshotted
    | `start_time` (e.g. its availability rule declared none). Anchors the
    | cancellation-window and no_show cutoffs to a sensible default instead of
    | `tour_date` midnight (spec-007 F5).
    */

    'default_start_time' => '09:00',

];
