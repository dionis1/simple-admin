<?php

return [
    // Phase 3 exposes bearer-token APIs only; web session cookies cannot authorize them.
    'stateful' => [],
    'guard' => [],
    'expiration' => null, // Every issued token has its own mandatory expires_at (at most 90 days).
    'token_prefix' => '',
    'routes' => false,
    'middleware' => [],
];
