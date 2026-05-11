<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedAccessToken extends Model
{
    protected $fillable = [
        'credential_id',
        'created_by',
        'token',
        'pin_hash',
        'expires_at',
        'max_uses',
        'use_count',
        'is_active',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active'  => 'boolean',
        ];
    }

    public function requiresPin(): bool
    {
        return $this->pin_hash !== null;
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->max_uses && $this->use_count >= $this->max_uses) {
            return false;
        }

        return true;
    }
}
