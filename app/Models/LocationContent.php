<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationContent extends Model
{
    protected $primaryKey = 'location_id';

    public $incrementing = false;

    protected $guarded = [];

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
