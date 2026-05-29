<?php

namespace App\Services;

use App\Models\Credential;
use App\Models\SharedAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SharedAccessTokenService
{
    public function __construct(private CredentialService $credentials) {}

    public function create(Credential $credential, User $creator, array $data): SharedAccessToken
    {
        $expiresAt = match (true) {
            isset($data['expires_at'])  => $data['expires_at'],
            isset($data['expires_in'])  => match ($data['expires_in']['unit']) {
                'minutes' => now()->addMinutes((int) $data['expires_in']['value']),
                'hours'   => now()->addHours((int) $data['expires_in']['value']),
            },
            default => null,
        };

        return SharedAccessToken::create([
            'credential_id' => $credential->id,
            'created_by'    => $creator->id,
            'token'         => Str::random(64),
            'pin_hash'      => isset($data['pin']) ? Hash::make($data['pin']) : null,
            'expires_at'    => $expiresAt,
            'max_uses'      => $data['max_uses'] ?? null,
        ]);
    }

    public function info(SharedAccessToken $token): array
    {
        $credential = $token->credential->load('image');

        return [
            'credential' => [
                'name'     => $credential->name,
                'type'     => $credential->type,
                'image_id' => $credential->image_id,
                'image'    => $this->mapImage($credential->image),
            ],
            'requires_pin' => $token->requiresPin(),
            'expires_at'   => $token->expires_at,
            'is_valid'     => $token->isValid(),
        ];
    }

    public function claim(SharedAccessToken $token, ?string $pin): array
    {
        $token->increment('use_count');

        $credential = $token->credential->load('image');

        return [
            'credential' => [
                'id'       => $credential->id,
                'name'     => $credential->name,
                'username' => $credential->username,
                'type'     => $credential->type,
                'image_id' => $credential->image_id,
                'image'    => $this->mapImage($credential->image),
            ],
            'password' => $this->credentials->decrypt($credential),
            'url'      => $credential->url,
            'token'    => [
                'expires_at' => $token->expires_at,
                'use_count'  => $token->use_count,
                'max_uses'   => $token->max_uses,
            ],
        ];
    }

    public function revoke(SharedAccessToken $token): void
    {
        $token->update(['is_active' => false]);
    }

    public function consume(SharedAccessToken $token): array
    {
        $token->increment('use_count');

        $credential = $token->credential->load('image');

        return [
            'credential' => [
                'id'       => $credential->id,
                'name'     => $credential->name,
                'username' => $credential->username,
                'type'     => $credential->type,
                'image_id' => $credential->image_id,
                'image'    => $this->mapImage($credential->image),
            ],
            'password' => $this->credentials->decrypt($credential),
            'url'      => $credential->url,
            'token'    => [
                'expires_at' => $token->expires_at,
                'use_count'  => $token->use_count,
                'max_uses'   => $token->max_uses,
            ],
        ];
    }

    private function mapImage(?\App\Models\Image $image): ?array
    {
        if (! $image) {
            return null;
        }

        return [
            'id'         => $image->id,
            'name'       => $image->name,
            'url'        => $image->url,
            'created_at' => $image->created_at,
        ];
    }
}
