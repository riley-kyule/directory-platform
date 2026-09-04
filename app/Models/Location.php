<?php

namespace App\Models;

use App\Services\PublicPageCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

        // Purge by re-fetching a disposable instance rather than touching the
        // saved model's own `content` relation here: on first creation this
        // fires before the paired LocationContent row exists, and caching a
        // premature null onto that relation would stick for the model's
        // lifetime (Eloquent never re-queries a relation once it's loaded).
        static::saved(fn (Location $location) => app(PublicPageCache::class)->forgetLocationId($location->id));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function content(): HasOne
    {
        return $this->hasOne(LocationContent::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(LocationAlias::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'primary_location_id');
    }

    public function sublocationProfiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'sublocation_id');
    }

    public function microLocationProfiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'micro_location_id');
    }

    public function isMicroLocation(): bool
    {
        return in_array($this->type, ['area', 'landmark'], true);
    }

    /**
     * Deliberately avoids the `content` property/loadMissing() when the
     * relation isn't already loaded, since either would cache the result
     * (even a miss) onto this instance for its lifetime — a problem right
     * after creation, when this can run before LocationContent exists yet.
     */
    public function publicPath(): string
    {
        // Public location routes are derived from the hierarchy. Never allow
        // editable content to point internal links or sitemap entries at an
        // unrelated/reserved path which the router cannot serve.
        return '/'.$this->full_slug.'-escorts';
    }

    protected function casts(): array
    {
        return ['is_indexable' => 'boolean'];
    }
}
