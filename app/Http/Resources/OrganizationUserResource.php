<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'role'               => $this->role,
            'account_type'       => $this->account_type,
            'two_factor_enabled' => $this->two_factor_enabled,
            'is_active'          => $this->is_active,
            'last_login_at'      => $this->last_login_at,
            'created_at'         => $this->created_at,
        ];
    }
}
