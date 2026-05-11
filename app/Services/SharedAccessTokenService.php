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
        return SharedAccessToken::create([
            'credential_id' => $credential->id,
            'created_by'    => $creator->id,
            'token'         => Str::random(64),
            'pin_hash'      => isset($data['pin']) ? Hash::make($data['pin']) : null,
            'expires_at'    => $data['expires_at'] ?? null,
            'max_uses'      => $data['max_uses'] ?? null,
        ]);
    }

    public function info(SharedAccessToken $token): array
    {
        $credential = $token->credential;

        return [
            'credential' => [
                'name' => $credential->name,
                'type' => $credential->type,
            ],
            'requires_pin' => $token->requiresPin(),
            'expires_at'   => $token->expires_at,
            'is_valid'     => $token->isValid(),
        ];
    }

    public function claim(SharedAccessToken $token, ?string $pin): array
    {
        $token->increment('use_count');

        $credential = $token->credential;

        return [
            'credential' => [
                'id'       => $credential->id,
                'name'     => $credential->name,
                'username' => $credential->username,
                'type'     => $credential->type,
            ],
            'password' => $this->credentials->decrypt($credential),
            'notes'    => $this->credentials->decryptNotes($credential),
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

        $credential = $token->credential;

        return [
            'credential' => [
                'id'       => $credential->id,
                'name'     => $credential->name,
                'username' => $credential->username,
                'type'     => $credential->type,
            ],
            'password' => $this->credentials->decrypt($credential),
            'notes'    => $this->credentials->decryptNotes($credential),
            'token'    => [
                'expires_at' => $token->expires_at,
                'use_count'  => $token->use_count,
                'max_uses'   => $token->max_uses,
            ],
        ];
    }
}
