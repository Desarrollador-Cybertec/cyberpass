<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'action'      => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id'   => $this->entity_id,
            'ip_address'  => $this->ip_address,
            'metadata'    => $this->metadata,
            'created_at'  => $this->created_at,
            'user'        => $this->whenLoaded('user', fn () => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),
            'organization' => $this->whenLoaded('organization', fn () => [
                'id'   => $this->organization->id,
                'name' => $this->organization->name,
            ]),
        ];
    }
}
