<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Metadatos de un token de integración.
 *
 * Lista blanca explícita de campos y no un toArray() del modelo: aunque Sanctum
 * ya marca `token` (el hash) como hidden, aquí se enumera lo que sale para que
 * añadir una columna al modelo no la publique sin querer.
 */
class IntegrationTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'abilities'    => (array) $this->abilities,
            'expires_at'   => $this->expires_at,
            'last_used_at' => $this->last_used_at,
            'created_at'   => $this->created_at,
        ];
    }
}
