<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function requests(): HasMany
    {
        return $this->hasMany(ProfilePackageRequest::class, 'requested_package_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProfilePackageAssignment::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
