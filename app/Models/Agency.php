<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Agency extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (Agency $agency) => $agency->public_id ??= (string) Str::uuid());
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'agency_profiles')
            ->withPivot(['assigned_by', 'assigned_at', 'unassigned_at'])
            ->withTimestamps();
    }
}
