<?php

return [
    // -------------------------------------------------------------------------
    // Admin panel
    // -------------------------------------------------------------------------
    'panel' => [
        'per_page' => env('NAWASARA_CITIZEN_PER_PAGE', 25),
    ],

    // -------------------------------------------------------------------------
    // NIK
    // -------------------------------------------------------------------------
    // Verification is manual for now: an officer checks the number against a
    // document and marks it verified. A Dukcapil/SIAK integration would slot
    // in behind the same model without changing anything above it.
    //
    // The number itself is encrypted at rest and never leaves the API; only
    // the `nik_verified` marker is exposed. That mirrors what Keycloak holds,
    // and for the same reason — see the migration.
    'nik' => [
        // Indonesian NIK is 16 digits. Kept configurable rather than hardcoded
        // so a validation change does not require touching the model.
        'length' => 16,
    ],

    // -------------------------------------------------------------------------
    // Notification inbox
    // -------------------------------------------------------------------------
    // What the app shows behind the bell. Kept for a fixed number of days and
    // the app TELLS the citizen that number, so someone looking for last
    // month's notice knows it is gone rather than failed to load. Raising it
    // only needs the app's text to change with it.
    'inbox' => [
        'retention_days' => (int) env('CITIZEN_INBOX_RETENTION_DAYS', 30),
    ],
];
