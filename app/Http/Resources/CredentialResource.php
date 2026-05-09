<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CredentialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'username'   => $this->username,
            'type'       => $this->type,
            'category_id' => $this->category_id,
            'password'   => $this->when($this->resource->password_plain !== null, $this->resource->password_plain),
            'notes'      => $this->when($this->resource->notes_plain !== null, $this->resource->notes_plain),
            'created_at' => $this->created_at,
        ];
    }
}
