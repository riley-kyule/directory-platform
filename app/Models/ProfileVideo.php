<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileVideo extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (ProfileVideo $video): void {
            if (! $video->public_id) {
                $video->public_id = (string) Str::uuid();
            }
        });
        static::saved(function (ProfileVideo $video): void {
            if ($video->status === 'approved' || $video->getOriginal('status') === 'approved') {
                $video->profile?->markPublicContentUpdated();
            }
        });
        static::deleted(function (ProfileVideo $video): void {
            if ($video->status === 'approved') {
                $video->profile?->markPublicContentUpdated();
            }
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function sourceFilename(): string
    {
        return 'source.'.($this->file_extension ?: 'mp4');
    }

    /** Direct, publicly addressable URL — only for an approved video on a public profile. */
    public function publicUrl(): ?string
    {
        if ($this->status !== 'approved') {
            return null;
        }

        return Storage::disk('profile_media')->url($this->storage_directory.'/'.$this->sourceFilename());
    }

    public function posterUrl(): ?string
    {
        if (! $this->has_poster || $this->status !== 'approved') {
            return null;
        }

        return Storage::disk('profile_media')->url($this->storage_directory.'/poster.jpg');
    }

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
            'file_size' => 'integer',
            'has_poster' => 'boolean',
        ];
    }
}
