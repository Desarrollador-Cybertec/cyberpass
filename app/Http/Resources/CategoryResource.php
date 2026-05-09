<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'division_id' => $this->division_id,
            'image'       => $this->when($this->image_id !== null, [
                'id'   => $this->image_id,
                'name' => $this->whenLoaded('image', fn () => $this->image->name),
                'url'  => $this->whenLoaded('image', fn () => $this->image->url),
            ]),
            'created_at'  => $this->created_at,
        ];
    }
}
