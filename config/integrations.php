<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tokens de integración
    |--------------------------------------------------------------------------
    |
    | Vida por defecto y máxima de un token de integración. Un token de larga
    | vida es, por construcción, un secreto duradero: el tope obliga a rotarlo.
    |
    */

    'token_default_days' => (int) env('INTEGRATION_TOKEN_DAYS', 365),

    'token_max_days' => (int) env('INTEGRATION_TOKEN_MAX_DAYS', 730),

    'max_tokens_per_user' => (int) env('INTEGRATION_MAX_TOKENS_PER_USER', 10),

    /*
    |--------------------------------------------------------------------------
    | Clientes reconocidos
    |--------------------------------------------------------------------------
    |
    | Lista blanca para la cabecera X-Integration-Client, que solo sirve para
    | enriquecer la auditoría. Se valida contra esta lista para que el portador
    | de un token no pueda falsear la procedencia de sus acciones.
    |
    */

    'clients' => ['axis'],

];
