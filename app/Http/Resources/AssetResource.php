<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'description'    => $this->description,
            'metadata'       => $this->metadata,
            'subcategory_id' => $this->subcategory_id,
            'created_at'     => $this->created_at,
        ];
    }
}
