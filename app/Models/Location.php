<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Location $location): void {
            if (! $location->public_id) {
                $location->public_id = (string) Str::uuid();
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'primary_location_id');
    }

    public function sublocationProfiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'sublocation_id');
    }

    protected function casts(): array
    {
        return ['is_indexable' => 'boolean'];
    }
}
