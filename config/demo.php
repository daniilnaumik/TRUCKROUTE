<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Demonstration accounts
    |--------------------------------------------------------------------------
    |
    | TruckRoute is distributed as a diploma demonstration project. Keep the
    | seeded account switcher independent from APP_DEBUG so production builds
    | can still be demonstrated without exposing Laravel debug information.
    |
    */
    'accounts_enabled' => (bool) env('DEMO_ACCOUNTS_ENABLED', true),
];
