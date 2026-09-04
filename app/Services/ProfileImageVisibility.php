<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\ProfileVideo;
use App\Support\MediaFilesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProfileImageVisibility
{
    public function __construct(private readonly PublicPageCache $pageCache) {}

    public function publish(Profile $profile): void
    {
        $this->publishImages($profile);
        $this->publishVideos($profile);
    }

    public function unpublish(Profile $profile): void
    {
        $this->unpublishImages($profile);
        $this->unpublishVideos($profile);
    }

    public function publishImages(Profile $profile): void
    {
        if (! $profile->status->isPublic()) {
            return;
        }

        $moved = 0;
        $profile->images()->whereIn('status', ['pending_review', 'reviewed'])->each(function ($image) use (&$moved): void {
            $this->move($image->storage_directory, 'media_review', 'profile_media', "image {$image->public_id}");
            $image->update(['status' => 'approved']);
            $moved++;
        });

        $this->flushIfChanged($profile, $moved);
    }

    public function unpublishImages(Profile $profile): void
    {
        $moved = 0;
        $profile->images()->where('status', 'approved')->each(function ($image) use (&$moved): void {
            $this->move($image->storage_directory, 'profile_media', 'media_review', "image {$image->public_id}");
            $image->update(['status' => 'pending_review']);
            $moved++;
        });

        $this->flushIfChanged($profile, $moved);
    }

    public function publishVideos(Profile $profile): void
    {
        if (! $profile->status->isPublic()) {
            return;
        }

        $moved = 0;
        $profile->videos()->whereIn('status', ['pending_review', 'reviewed'])->each(function (ProfileVideo $video) use (&$moved): void {
            $this->move($video->storage_directory, 'media_review', 'profile_media', "video {$video->public_id}");
            $video->update(['status' => 'approved']);
            $moved++;
        });

        $this->flushIfChanged($profile, $moved);
    }

    public function unpublishVideos(Profile $profile): void
    {
        $moved = 0;
        $profile->videos()->where('status', 'approved')->each(function (ProfileVideo $video) use (&$moved): void {
            $this->move($video->storage_directory, 'profile_media', 'media_review', "video {$video->public_id}");
            $video->update(['status' => 'pending_review']);
            $moved++;
        });

        $this->flushIfChanged($profile, $moved);
    }

    private function flushIfChanged(Profile $profile, int $moved): void
    {
        if ($moved > 0) {
            $this->pageCache->forgetForProfile($profile);
        }
    }

    private function move(string $relativePath, string $sourceDiskName, string $destinationDiskName, string $label): void
    {
        $source = Storage::disk($sourceDiskName)->path($relativePath);
        $destination = Storage::disk($destinationDiskName)->path($relativePath);

        if (! is_dir($source)) {
            if (is_dir($destination)) {
                return; // Already where we want it (a retried publish/unpublish).
            }
            throw new RuntimeException("Media directory is missing for {$label}.");
        }
        if (is_dir($destination)) {
            throw new RuntimeException("{$label} already has media at the destination.");
        }

        MediaFilesystem::moveDirectory($source, $destination);
    }
}
