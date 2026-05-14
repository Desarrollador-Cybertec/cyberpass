<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
     * Listar los orígenes exactos del frontend. No usar '*' cuando
     * supports_credentials es true (el browser lo rechaza).
     * Configura CORS_ALLOWED_ORIGINS en el .env de producción.
     */
    'allowed_origins' => array_filter(
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200'))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-XSRF-TOKEN', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Requerido para que el browser envíe cookies HttpOnly cross-origin
    'supports_credentials' => true,

];
       