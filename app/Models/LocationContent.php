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
            if (! $content->location) {
                return;
            }
            $cache = app(PublicPageCache::class);
            $cache->forgetForLocation($content->location);
            // Publishing/unpublishing a location adds or removes it from
            // /locations and the sidebar tree across every public page.
            if ($content->wasChanged('content_status')) {
                $cache->forgetAll();
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Parses the free-text FAQ blob (a "Q: ...\nA: ..." convention, one pair
     * per block) into structured pairs, for both display and FAQPage schema.
     *
     * @return array<int, array{question: string, answer: string}>
     */
    public function faqPairs(): array
    {
        $content = (string) data_get($this->faq_content, 'content', '');
        if (trim($content) === '') {
            return [];
        }

        preg_match_all('/Q:\s*(.+?)\s*\n+A:\s*(.+?)(?=\n\s*Q:|\z)/s', $content, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(fn (array $match) => ['question' => trim($match[1]), 'answer' => trim($match[2])])
            ->filter(fn (array $pair) => $pair['question'] !== '' && $pair['answer'] !== '')
            ->values()
            ->all();
    }

    protected function casts(): array
    {
        return [
            'faq_content' => 'array',
            'last_reviewed_at' => 'datetime',
        ];
    }
}
