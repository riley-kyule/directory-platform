<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\ProfileVideo;
use App\Support\MediaFilesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProfileImageVisibility
{
    public function publish(Profile $profile): void
    {
        if (! $profile->status->isPublic()) {
            return;
        }

        $profile->images()->where('status', 'pending_review')->each(function ($image): void {
            $this->move($image->storage_directory, 'media_review', 'profile_media', "image {$image->public_id}");
            $image->update(['status' => 'approved']);
        });

        $this->publishVideos($profile);
    }

    public function unpublish(Profile $profile): void
    {
        $profile->images()->where('status', 'approved')->each(function ($image): void {
            $this->move($image->storage_directory, 'profile_media', 'media_review', "image {$image->public_id}");
            $image->update(['status' => 'pending_review']);
        });

        $this->unpublishVideos($profile);
    }

    public function publishVideos(Profile $profile): void
    {
        if (! $profile->status->isPublic()) {
            return;
        }

        $profile->videos()->where('status', 'pending_review')->each(function (ProfileVideo $video): void {
            $this->move($video->storage_directory, 'media_review', 'profile_media', "video {$video->public_id}");
            $video->update(['status' => 'approved']);
        });
    }

    public function unpublishVideos(Profile $profile): void
    {
        $profile->videos()->where('status', 'approved')->each(function (ProfileVideo $video): void {
            $this->move($video->storage_directory, 'profile_media', 'media_review', "video {$video->public_id}");
            $video->update(['status' => 'pending_review']);
        });
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
