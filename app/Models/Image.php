<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Image extends Model
{
    protected $fillable = [
        'name',
        'url',
    ];

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }
}
