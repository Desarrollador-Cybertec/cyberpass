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
        'user_id',
        'created_by',
        'image_id',
        'name',
        'username',
        'email',
        'nextcloud_account',
        'encrypted_password',
        'iv',
        'url',
        'type',
    ];

    protected $hidden = [
        'encrypted_password',
        'iv',
    ];

    // Aqui vivia un `public ?string $password_plain` que CredentialResource
    // serializaba cuando no era null. Nadie lo asignaba nunca, pero dejaba
    // abierta la posibilidad de que un texto plano acabara en los listados
    // paginados. El texto plano solo sale por los endpoints /reveal.

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
