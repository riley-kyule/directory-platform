<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Privacy-safe daily contact totals. This model intentionally stores no
 * visitor, session, network, device, or user-agent identifier.
 */
#[Fillable(['event_date', 'profile_id', 'channel', 'placement', 'contact_count'])]
class ProfileConversionDaily extends Model
{
    protected $table = 'profile_conversion_daily';

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'contact_count' => 'integer',
        ];
    }
}
