<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['alias', 'normalized_alias'])]
class LocationAlias extends Model
{
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
