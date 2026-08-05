<?php

namespace App\Models;

use App\Services\DirectorySettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class PolicyVersion extends Model
{
    public const CACHE_KEY = 'policies:latest-published:v2';

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
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
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

    /**
     * Policy content is authored with {{platform_name}}/{{support_email}}
     * tokens so one generic template can be reused across installs and stays
     * in sync when the admin renames the site — substituted at render time,
     * not at save time, so editors always see and edit the raw tokens.
     */
    public function renderedContent(): string
    {
        return $this->substituteVariables($this->content);
    }

    public function renderedSummary(): ?string
    {
        return $this->summary ? $this->substituteVariables($this->summary) : null;
    }

    private function substituteVariables(string $text): string
    {
        $settings = app(DirectorySettings::class);
        $platformName = $settings->string('site.platform_name') ?: config('app.name');
        $supportEmail = $settings->string('site.support_email') ?: ('support@'.parse_url((string) config('app.url'), PHP_URL_HOST));

        return strtr($text, [
            '{{platform_name}}' => $platformName,
            '{{support_email}}' => $supportEmail,
        ]);
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'requires_reacceptance' => 'boolean',
        ];
    }
}
