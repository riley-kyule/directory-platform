<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\OnboardingStatus;
use App\Enums\ProviderType;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'email',
    'password',
    'account_type',
    'provider_type',
    'onboarding_status',
    'onboarding_started_at',
    'onboarding_completed_at',
    'last_onboarding_activity_at',
    'last_seen_at',
    'status',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->public_id ??= (string) Str::uuid();
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'owner_user_id');
    }

    public function agency(): HasOne
    {
        return $this->hasOne(Agency::class, 'owner_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    public function policyAcceptances(): HasMany
    {
        return $this->hasMany(PolicyAcceptance::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    public function isPrivileged(): bool
    {
        return $this->roles()->whereIn('slug', ['admin', 'csr', 'seo'])->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'provider_type' => ProviderType::class,
            'onboarding_status' => OnboardingStatus::class,
            'email_verified_at' => 'datetime',
            'onboarding_started_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'last_onboarding_activity_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
