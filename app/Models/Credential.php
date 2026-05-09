<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Credential extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'organization_id',
        'created_by',
        'image_id',
        'name',
        'username',
        'encrypted_password',
        'iv',
        'notes_encrypted',
        'iv_notes',
        'type',
    ];

    protected $hidden = [
        'encrypted_password',
        'iv',
        'notes_encrypted',
        'iv_notes',
    ];

    // Populated by CredentialService::decrypt() before passing to the resource
    public ?string $password_plain = null;
    public ?string $notes_plain    = null;

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CredentialVersion::class);
    }

    public function sharedTokens(): HasMany
    {
        return $this->hasMany(SharedAccessToken::class);
    }
}
