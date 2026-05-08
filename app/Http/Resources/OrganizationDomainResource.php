<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationDomainResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'domain'      => $this->domain,
            'is_verified' => $this->is_verified,
            'created_at'  => $this->created_at,
        ];
    }
}
