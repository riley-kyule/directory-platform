<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfileImageRequest;
use App\Jobs\ProcessProfileImage;
use App\Models\Profile;
use App\Models\ProfileImage;
use App\Services\ProfileImageLimit;
use App\Services\ProfileMediaAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileMediaController extends Controller
{
    public function __construct(
        private readonly ProfileMediaAccess $access,
        private readonly ProfileImageLimit $imageLimit,
    ) {}

    public function index(Profile $profile): View
    {
        abort_unless($this->access->canView(request()->user(), $profile), 403);

        return view('onboarding.media', [
            'profile' => $profile->load('images'),
            'limit' => $this->imageLimit->for($profile),
            'canManage' => $this->access->canManage(request()->user(), $profile),
        ]);
    }

    public function store(StoreProfileImageRequest $request, Profile $profile): RedirectResponse
    {
        $file = $request->file('image');
        $dimensions = getimagesize($file->getRealPath());

        $image = DB::transaction(function () use ($profile, $file, $dimensions): ProfileImage {
            $profile = Profile::query()->lockForUpdate()->findOrFail($profile->id);
            $limit = $this->imageLimit->for($profile);
            $currentCount = $profile->images()->whereNotIn('status', ['rejected', 'private'])->count();
            abort_if($limit < 1 || $currentCount >= $limit, 422, 'The package image limit has been reached.');

            $hash = hash_file('sha256', $file->getRealPath());
            abort_if($profile->images()->where('exact_hash', $hash)->exists(), 422, 'This image has already been uploaded to the profile.');

            $publicId = (string) Str::uuid();
            $quarantinePath = $profile->public_id.'/'.$publicId.'.upload';
            Storage::disk('quarantine')->putFileAs($profile->public_id, $file, $publicId.'.upload');

            return $profile->images()->create([
                'public_id' => $publicId,
                'storage_directory' => $quarantinePath,
                'sort_order' => ($profile->images()->max('sort_order') ?? 0) + 10,
                'status' => 'quarantined',
                'width' => $dimensions[0],
                'height' => $dimensions[1],
                'aspect_ratio' => $dimensions[0] / $dimensions[1],
                'mime_type' => $dimensions['mime'],
                'file_size' => $file->getSize(),
                'exact_hash' => $hash,
            ]);
        });

        ProcessProfileImage::dispatch($image->id)->afterCommit();

        return back()->with('status', 'Image uploaded securely and queued for processing.');
    }

    public function preview(Profile $profile, ProfileImage $image, string $slot): BinaryFileResponse
    {
        abort_unless($image->profile_id === $profile->id, 404);
        abort_unless($this->access->canView(request()->user(), $profile), 403);
        abort_unless(in_array($slot, ['thumb', 'card', 'profile', 'full'], true), 404);
        abort_unless(in_array($image->status, ['pending_review', 'approved'], true), 404);

        $derivative = $image->derivatives[$slot] ?? null;
        abort_unless($derivative, 404);
        $disk = $image->status === 'approved' ? Storage::disk('profile_media') : Storage::disk('media_review');
        $path = $image->storage_directory.'/'.$derivative['file'];
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Profile $profile, ProfileImage $image): RedirectResponse
    {
        abort_unless($image->profile_id === $profile->id, 404);
        abort_unless($this->access->canManage(request()->user(), $profile), 403);
        abort_if($image->status === 'processing', 409, 'Wait for image processing to finish before removing it.');

        if ($image->status === 'quarantined') {
            Storage::disk('quarantine')->delete($image->storage_directory);
        } elseif ($image->status === 'pending_review') {
            Storage::disk('media_review')->deleteDirectory($image->storage_directory);
        } elseif ($image->storage_directory) {
            Storage::disk('profile_media')->deleteDirectory($image->storage_directory);
        }

        $image->delete();

        return back()->with('status', 'Image removed.');
    }
}
