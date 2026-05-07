<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialVersion extends Model
{
    protected $fillable = [
        'credential_id',
        'changed_by',
        'username',
        'encrypted_password',
        'iv',
    ];

    protected $hidden = [
        'encrypted_password',
        'iv',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
