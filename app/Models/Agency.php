<?php

namespace App\Models;

use App\Enums\ProfileStatus;
use Illuminate\Database\Eloquent\Builder;
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
        static::creating(function (Agency $agency): void {
            if (! $agency->public_id) {
                $agency->public_id = (string) Str::uuid();
            }
        });
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

    public function publicProfiles(): BelongsToMany
    {
        return $this->profiles()
            ->wherePivotNull('unassigned_at')
            ->where('profiles.status', ProfileStatus::Active->value)
            ->where(fn (Builder $query) => $query
                ->whereNull('profiles.expires_at')
                ->orWhere('profiles.expires_at', '>', now()))
            ->whereHas('packageAssignments', fn (Builder $query) => $query
                ->where('status', 'active')
                ->where('expires_at', '>', now()));
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereHas('publicProfiles');
    }
}
