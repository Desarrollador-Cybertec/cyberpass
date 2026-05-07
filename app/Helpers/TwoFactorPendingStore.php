<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TwoFactorPendingStore
{
    private const TTL_MINUTES = 5;

    private const PREFIX = '2fa_pending:';

    public static function store(int $userId): string
    {
        $token = Str::random(64);
        Cache::put(self::PREFIX.$token, $userId, now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    public static function retrieve(string $token): ?int
    {
        return Cache::get(self::PREFIX.$token);
    }

    public static function forget(string $token): void
    {
        Cache::forget(self::PREFIX.$token);
    }
}
