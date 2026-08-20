<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Privacy-safe daily profile-page totals. No visitor, session, network,
 * device, referrer, or user-agent value is persisted.
 */
#[Fillable(['event_date', 'profile_id', 'view_count'])]
class ProfileViewDaily extends Model
{
    protected $table = 'profile_view_daily';

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'view_count' => 'integer',
        ];
    }
}
