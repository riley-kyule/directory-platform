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
        static::saved(function (ProfileImage $image): void {
            if ($image->status === 'approved' || $image->getOriginal('status') === 'approved') {
                $image->profile?->markPublicContentUpdated();
            }
        });
        static::deleted(function (ProfileImage $image): void {
            if ($image->status === 'approved') {
                $image->profile?->markPublicContentUpdated();
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

    /** @param list<string> $slots */
    public function responsiveSrcset(array $slots = ['thumb', 'card', 'profile', 'full']): ?string
    {
        if ($this->status !== 'approved') {
            return null;
        }

        $sources = collect($slots)
            ->map(function (string $slot): ?array {
                $derivative = $this->derivatives[$slot] ?? null;
                if (! is_array($derivative) || ! isset($derivative['file'], $derivative['width'])) {
                    return null;
                }

                return [
                    'url' => Storage::disk('profile_media')->url($this->storage_directory.'/'.$derivative['file']),
                    'width' => (int) $derivative['width'],
                ];
            })
            ->filter()
            ->unique('width')
            ->sortBy('width');

        return $sources->isEmpty()
            ? null
            : $sources->map(fn (array $source) => $source['url'].' '.$source['width'].'w')->implode(', ');
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
