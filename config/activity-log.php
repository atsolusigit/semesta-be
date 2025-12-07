<?php

return [
    'enabled' => env('ACTIVITY_LOG_ENABLED', true),

    'max_payload_bytes' => env('ACTIVITY_LOG_MAX_PAYLOAD', 8000),
    'max_body_bytes' => env('ACTIVITY_LOG_MAX_BODY', 2000),
    'max_curl_length' => env('ACTIVITY_LOG_MAX_CURL', 4000),

    'skip_paths' => [
        'telescope*',
        'horizon*',
        'debugbar*',
        'sanctum/csrf-cookie',
        'up',
        'health',
    ],

    'redact_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'authorization',
        'api_key',
        'x-api-key',
        'cookie',
        'remember_token',
    ],

    'redact_headers' => [
        'authorization',
        'cookie',
        'x-csrf-token',
        'x-xsrf-token',
    ],
];
