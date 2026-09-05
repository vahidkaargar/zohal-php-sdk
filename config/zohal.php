<?php

declare(strict_types=1);

return [
    'token' => env('ZOHAL_TOKEN'),

    'base_uri' => env('ZOHAL_BASE_URI', 'https://service.zohal.io/api/v0'),

    // Zohal's biometric (video-auth) service may be issued a separate
    // bearer token from the rest of the API. Falls back to `token` above
    // when not set.
    'biometric_token' => env('ZOHAL_BIOMETRIC_TOKEN'),
];
