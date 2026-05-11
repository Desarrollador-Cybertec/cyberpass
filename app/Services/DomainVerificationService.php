<?php

namespace App\Services;

use App\Models\OrganizationDomain;
use Illuminate\Support\Str;

class DomainVerificationService
{
    private const TOKEN_PREFIX = 'cyberpass-verify=';

    private const TTL_HOURS = 72;

    public function initiate(OrganizationDomain $domain): string
    {
        $token = Str::random(32);

        $domain->update([
            'verification_token'      => $token,
            'verification_expires_at' => now()->addHours(self::TTL_HOURS),
        ]);

        return self::TOKEN_PREFIX.$token;
    }

    public function confirm(OrganizationDomain $domain): bool
    {
        if (! $domain->verification_token || ! $domain->verification_expires_at) {
            return false;
        }

        if ($domain->verification_expires_at->isPast()) {
            return false;
        }

        $expectedRecord = self::TOKEN_PREFIX.$domain->verification_token;

        $records = @dns_get_record($domain->domain, DNS_TXT) ?: [];

        $found = collect($records)->contains(function ($record) use ($expectedRecord) {
            $txt = $record['txt'] ?? $record['entries'][0] ?? '';

            return $txt === $expectedRecord;
        });

        if ($found) {
            $domain->update([
                'is_verified'             => true,
                'verification_token'      => null,
                'verification_expires_at' => null,
            ]);
        }

        return $found;
    }
}
