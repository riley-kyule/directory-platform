<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Review extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (Review $review) => $review->public_id ??= (string) Str::uuid());
        static::saved(function (Review $review): void {
            if ($review->status === 'published' || $review->getOriginal('status') === 'published') {
                $review->profile?->markPublicContentUpdated();
            }
        });
        static::deleted(function (Review $review): void {
            if ($review->status === 'published') {
                $review->profile?->markPublicContentUpdated();
            }
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public static function duplicateExists(int $profileId, string $emailHash): bool
    {
        return static::query()
            ->where('profile_id', $profileId)
            ->where('reviewer_email_hash', $emailHash)
            ->whereIn('status', ['pending', 'published'])
            ->where('created_at', '>=', now()->subDays(config('operations.review_duplicate_window_days')))
            ->exists();
    }

    protected function casts(): array
    {
        return [
            'reviewer_email' => 'encrypted',
            'moderated_at' => 'datetime',
        ];
    }
}
