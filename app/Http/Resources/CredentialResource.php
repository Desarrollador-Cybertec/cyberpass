<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CredentialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'username'    => $this->username,
            'type'        => $this->type,
            'category_id' => $this->category_id,
            'image'       => $this->when($this->image_id !== null, [
                'id'   => $this->image_id,
                'name' => $this->whenLoaded('image', fn () => $this->image->name),
                'url'  => $this->whenLoaded('image', fn () => $this->image->url),
            ]),
            // Sin clave 'password': este recurso nunca expone el texto plano.
            // El secreto solo se devuelve por los endpoints /reveal.
            'url'         => $this->url,
            'created_at'  => $this->created_at,
        ];
    }
}
