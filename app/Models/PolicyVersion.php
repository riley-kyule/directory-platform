<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class PolicyVersion extends Model
{
    public const TYPES = [
        'terms' => 'Terms of Use',
        'privacy' => 'Privacy Policy',
        'provider' => 'Provider Policy',
        'media' => 'Media Policy',
        'agency' => 'Agency Policy',
    ];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('policies:latest-published'));
        static::deleted(fn () => Cache::forget('policies:latest-published'));
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(PolicyAcceptance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function label(): string
    {
        return self::TYPES[$this->policy_type] ?? str($this->policy_type)->headline()->toString();
    }

    public function publicRoute(): string
    {
        return route('policies.'.$this->policy_type);
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'requires_reacceptance' => 'boolean',
        ];
    }
}
