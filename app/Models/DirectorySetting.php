<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class DirectorySetting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(fn (DirectorySetting $setting) => Cache::forget('directory-setting:'.$setting->key));
        static::deleted(fn (DirectorySetting $setting) => Cache::forget('directory-setting:'.$setting->key));
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
