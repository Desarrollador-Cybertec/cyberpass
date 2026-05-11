<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationDomain extends Model
{
    protected $fillable = [
        'organization_id',
        'domain',
        'is_verified',
        'verification_token',
        'verification_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_verified'             => 'boolean',
            'verification_expires_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
