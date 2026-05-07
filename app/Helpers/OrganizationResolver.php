<?php

namespace App\Helpers;

use App\Models\Organization;
use App\Models\OrganizationDomain;

class OrganizationResolver
{
    public static function fromEmail(string $email): ?Organization
    {
        $domain = email_domain($email);

        $orgDomain = OrganizationDomain::with('organization')
            ->where('domain', $domain)
            ->where('is_verified', true)
            ->first();

        return $orgDomain?->organization;
    }

    public static function isEnterpriseDomain(string $email): bool
    {
        return static::fromEmail($email) !== null;
    }
}
