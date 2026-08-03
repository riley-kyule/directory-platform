<?php

namespace App\Models;

use App\Services\PublicPageCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageContent extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(function (PageContent $content): void {
            $cache = app(PublicPageCache::class);

            match ($content->page_key) {
                // Homepage content also supplies the VIP/Premium/Basic/New section
                // labels shown on every location page; those aren't purged here and
                // self-heal within PublicPageCache's stale window instead.
                'homepage' => $cache->forget(route('directory.home')),
                'agencies' => $cache->forget(route('directory.agencies.index')),
                default => null,
            };
        });
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return ['listing_sections' => 'array'];
    }
}
