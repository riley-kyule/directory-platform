<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
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

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subHours(config('operations.moderation_appeal_sla_hours')));
    }

    public function slaDeadline(): Carbon
    {
        return $this->created_at->copy()->addHours(config('operations.moderation_appeal_sla_hours'));
    }

    public function slaState(): string
    {
        if ($this->status !== 'pending') {
            return 'closed';
        }

        return $this->slaDeadline()->isPast() ? 'overdue' : 'on_track';
    }

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'sla_escalated_at' => 'datetime'];
    }
}
