<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\ProfileImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProfileImageVisibility
{
    public function publish(Profile $profile): void
    {
        if (! $profile->status->isPublic()) {
            return;
        }

        $profile->images()->where('status', 'pending_review')->each(function (ProfileImage $image): void {
            $this->move($image, 'media_review', 'profile_media');
            $image->update(['status' => 'approved']);
        });
    }

    public function unpublish(Profile $profile): void
    {
        $profile->images()->where('status', 'approved')->each(function (ProfileImage $image): void {
            $this->move($image, 'profile_media', 'media_review');
            $image->update(['status' => 'pending_review']);
        });
    }

    private function move(ProfileImage $image, string $sourceDiskName, string $destinationDiskName): void
    {
        $sourceDisk = Storage::disk($sourceDiskName);
        $destinationDisk = Storage::disk($destinationDiskName);
        $source = $sourceDisk->path($image->storage_directory);
        $destination = $destinationDisk->path($image->storage_directory);

        if (! is_dir($source)) {
            throw new RuntimeException("Derivative directory is missing for image {$image->public_id}.");
        }
        if (! is_dir(dirname($destination)) && ! mkdir(dirname($destination), 0755, true) && ! is_dir(dirname($destination))) {
            throw new RuntimeException('The destination media directory could not be created.');
        }
        if (is_dir($destination) || ! rename($source, $destination)) {
            throw new RuntimeException("Image {$image->public_id} could not be moved atomically.");
        }
    }
}
