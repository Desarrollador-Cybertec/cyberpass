<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Extiende el modelo de Sanctum con la columna `type`.
 *
 * NO declarar la clase como final: Sanctum::actingAs() la mockea con Mockery
 * en los tests y un mock no puede extender una clase final.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /** Token de sesion humana: expira segun config('sanctum.expiration'). */
    public const TYPE_SESSION = 'session';

    /**
     * Token de integracion maquina-a-maquina (Axis). Ignora el tope global de
     * expiracion y solo respeta su propio expires_at — ver el callback
     * Sanctum::authenticateAccessTokensUsing() en AppServiceProvider.
     */
    public const TYPE_INTEGRATION = 'integration';

    public function isIntegration(): bool
    {
        return $this->type === self::TYPE_INTEGRATION;
    }

    public function isSession(): bool
    {
        return $this->type !== self::TYPE_INTEGRATION;
    }

    /** @param  Builder<self>  $query */
    public function scopeSessions(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SESSION);
    }

    /** @param  Builder<self>  $query */
    public function scopeIntegrations(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_INTEGRATION);
    }
}
