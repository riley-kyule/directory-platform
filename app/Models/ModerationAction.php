<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModerationAction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProfileReport::class, 'report_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(ModerationAppeal::class);
    }
}
