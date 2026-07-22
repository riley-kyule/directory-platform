<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileImage extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (ProfileImage $image): void {
            if (! $image->public_id) {
                $image->public_id = (string) Str::uuid();
            }
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function publicUrl(string $slot = 'card'): ?string
    {
        $file = $this->derivatives[$slot]['file'] ?? null;

        if ($this->status !== 'approved' || ! is_string($file)) {
            return null;
        }

        return Storage::disk('profile_media')->url($this->storage_directory.'/'.$file);
    }

    protected function casts(): array
    {
        return [
            'derivatives' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
            'aspect_ratio' => 'float',
        ];
    }
}
