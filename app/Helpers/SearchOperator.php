<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

/**
 * Búsquedas por texto portables entre motores.
 *
 * Postgres necesita ILIKE para ser insensible a mayúsculas; sqlite y MySQL ya
 * lo son con LIKE sobre ASCII. Sin esto, cualquier búsqueda revienta con un
 * error de sintaxis en el entorno local y en la suite de tests (ambos sqlite).
 */
final class SearchOperator
{
    /** Operador LIKE insensible a mayúsculas para el driver activo. */
    public static function like(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    /** Envuelve el término en comodines, escapando los que traiga el usuario. */
    public static function wrap(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
    }
}
