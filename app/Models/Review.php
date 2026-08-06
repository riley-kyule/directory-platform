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

    protected function casts(): array
    {
        return [
            'reviewer_email' => 'encrypted',
            'moderated_at' => 'datetime',
        ];
    }
}
