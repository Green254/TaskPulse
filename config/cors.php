<?php

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:3000,http://localhost:4173,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:4173,http://127.0.0.1:5173'
    ))
)));

$allowedOriginPatterns = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', '/'],
    'allowed_origins' => $allowedOrigins,
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins_patterns' => $allowedOriginPatterns,
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
