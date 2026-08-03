<?php

namespace App\Models;

use App\Services\PublicPageCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationContent extends Model
{
    protected $primaryKey = 'location_id';

    public $incrementing = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(function (LocationContent $content): void {
            if ($content->location) {
                app(PublicPageCache::class)->forgetForLocation($content->location);
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    protected function casts(): array
    {
        return [
            'faq_content' => 'array',
            'last_reviewed_at' => 'datetime',
        ];
    }
}
