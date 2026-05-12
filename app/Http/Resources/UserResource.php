<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'email'                 => $this->email,
            'role'                  => $this->role,
            'account_type'          => $this->account_type,
            'two_factor_enabled'    => $this->two_factor_enabled,
            'is_active'             => $this->is_active,
            'last_login_at'         => $this->last_login_at,
            'organization'          => $this->whenLoaded('organization', fn () => $this->organization ? [
                'id'   => $this->organization->id,
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
            ] : null),
            'vault_enabled'         => true,
        ];
    }
}
