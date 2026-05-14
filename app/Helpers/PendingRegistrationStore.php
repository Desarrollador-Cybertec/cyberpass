<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PendingRegistrationStore
{
    private const TTL_MINUTES = 10;

    private const TOKEN_PREFIX = 'registration_pending:';

    private const EMAIL_PREFIX = 'registration_pending_email:';

    public static function store(array $payload): string
    {
        $email = self::normalizeEmail($payload['email']);
        $existingToken = Cache::get(self::EMAIL_PREFIX.$email);

        if (is_string($existingToken)) {
            self::forget($existingToken);
        }

        $token = Str::random(64);

        self::putTokenPayload($token, $payload);
        Cache::put(self::EMAIL_PREFIX.$email, $token, now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    public static function retrieve(string $token): ?array
    {
        return Cache::get(self::TOKEN_PREFIX.$token);
    }

    public static function update(string $token, array $payload): void
    {
        $current = self::retrieve($token);

        if (! is_array($current)) {
            return;
        }

        $updated = array_merge($current, $payload);

        self::putTokenPayload($token, $updated);
        Cache::put(self::EMAIL_PREFIX.self::normalizeEmail($updated['email']), $token, now()->addMinutes(self::TTL_MINUTES));
    }

    public static function forget(string $token): void
    {
        $current = self::retrieve($token);

        if (is_array($current) && isset($current['email'])) {
            Cache::forget(self::EMAIL_PREFIX.self::normalizeEmail($current['email']));
        }

        Cache::forget(self::TOKEN_PREFIX.$token);
    }

    private static function putTokenPayload(string $token, array $payload): void
    {
        Cache::put(self::TOKEN_PREFIX.$token, $payload, now()->addMinutes(self::TTL_MINUTES));
    }

    private static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
