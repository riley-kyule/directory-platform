<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ModerationAppeal extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (ModerationAppeal $appeal) => $appeal->public_id ??= (string) Str::uuid());
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function moderationAction(): BelongsTo
    {
        return $this->belongsTo(ModerationAction::class);
    }

    public function appellant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appellant_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
