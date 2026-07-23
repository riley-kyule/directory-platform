<?php

namespace App\Models;

use App\Enums\ProfileStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Profile extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Profile $profile): void {
            $profile->public_id ??= (string) Str::uuid();
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function primaryLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'primary_location_id');
    }

    public function sublocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'sublocation_id');
    }

    public function agency(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class, 'agency_profiles')
            ->withPivot(['assigned_by', 'assigned_at', 'unassigned_at'])
            ->withTimestamps();
    }

    public function currentAgency(): BelongsToMany
    {
        return $this->agency()->wherePivotNull('unassigned_at');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ProfileContactMethod::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(TaxonomyOption::class, 'gender_option_id');
    }

    public function ethnicity(): BelongsTo
    {
        return $this->belongsTo(TaxonomyOption::class, 'ethnicity_option_id');
    }

    public function build(): BelongsTo
    {
        return $this->belongsTo(TaxonomyOption::class, 'build_option_id');
    }

    public function bustSize(): BelongsTo
    {
        return $this->belongsTo(TaxonomyOption::class, 'bust_size_option_id');
    }

    public function details(): HasOne
    {
        return $this->hasOne(ProfileDetail::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ProfileRate::class)->where('is_active', true)->orderBy('sort_order');
    }

    public function packageRequests(): HasMany
    {
        return $this->hasMany(ProfilePackageRequest::class);
    }

    public function packageAssignments(): HasMany
    {
        return $this->hasMany(ProfilePackageAssignment::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProfileImage::class)->orderBy('sort_order');
    }

    public function currentPackageAssignment(): HasOne
    {
        return $this->hasOne(ProfilePackageAssignment::class)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latestOfMany('starts_at');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(TaxonomyOption::class, 'profile_services', 'profile_id', 'service_option_id');
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(TaxonomyOption::class, 'profile_languages', 'profile_id', 'language_option_id');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', ProfileStatus::Active->value)
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->whereHas('packageAssignments', fn (Builder $query) => $query
                ->where('status', 'active')
                ->where('expires_at', '>', now()));
    }

    public function activityLabel(): ?string
    {
        $account = $this->owner ?? $this->currentAgency->first()?->owner;

        if (! $account?->last_seen_at) {
            return null;
        }

        if ($account->last_seen_at->gte(now()->subMinutes(config('directory.activity.online_minutes')))) {
            return 'online';
        }

        if ($account->last_seen_at->gte(now()->subMinutes(config('directory.activity.recently_active_minutes')))) {
            return 'recently_active';
        }

        return null;
    }

    protected function casts(): array
    {
        return [
            'status' => ProfileStatus::class,
            'date_of_birth' => 'date',
            'allows_incall' => 'boolean',
            'allows_outcall' => 'boolean',
            'published_at' => 'datetime',
            'last_activated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
