<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SharedAccessTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        return [
            'id'           => $this->id,
            'token'        => $this->token,
            'share_url'    => "{$frontendUrl}/shared/{$this->token}",
            'requires_pin' => $this->requiresPin(),
            'expires_at'   => $this->expires_at,
            'max_uses'     => $this->max_uses,
            'use_count'    => $this->use_count,
            'is_active'    => $this->is_active,
            'is_valid'     => $this->isValid(),
            'created_by'   => $this->created_by,
            'created_at'   => $this->created_at,
        ];
    }
}
