<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CredentialVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'username'   => $this->username,
            'changed_by' => [
                'id'   => $this->changed_by,
                'name' => $this->whenLoaded('changedBy', fn () => $this->changedBy->name),
            ],
            'created_at' => $this->created_at,
        ];
    }
}
