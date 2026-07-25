<?php

namespace App\Http\Resources;

use App\Services\IntegrationScopeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Credencial vista por un cliente de integración.
 *
 * Sin clave `password`, por construcción: el secreto solo sale por /reveal.
 */
class IntegrationCredentialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'username'    => $this->username,
            'url'         => $this->url,
            'type'        => $this->type,
            'category'    => [
                'id'    => $this->category_id,
                'name'  => $this->whenLoaded('category', fn () => $this->category?->name),
                'scope' => $this->user_id !== null
                    ? IntegrationScopeResolver::SCOPE_PERSONAL
                    : IntegrationScopeResolver::SCOPE_ORGANIZATION,
            ],
            'image_id'    => $this->image_id,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
