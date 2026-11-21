<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| The SPA is deployed from a separate origin, so the browser pre-flights
| every request. Set CORS_ALLOWED_ORIGINS to a comma separated list of
| trusted origins (e.g. "https://hms.example.org,http://localhost:5173").
|
| When the variable is empty the default stays "*", which is safe here only
| because the API uses bearer tokens and never cookie-based sessions.
|
*/

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins !== [] ? $allowedOrigins : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
