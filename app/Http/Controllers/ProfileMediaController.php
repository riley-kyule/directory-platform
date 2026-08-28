<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfileImageRequest;
use App\Http\Requests\StoreProfileVideoRequest;
use App\Jobs\ProcessProfileImage;
use App\Jobs\ProcessProfileVideo;
use App\Models\Profile;
use App\Models\ProfileImage;
use App\Models\ProfileVideo;
use App\Services\PolicyAcceptanceService;
use App\Services\ProfileImageLimit;
use App\Services\ProfileMediaAccess;
use App\Services\ProfileVideoLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ProfileMediaController extends Controller
{
    public function __construct(
        private readonly ProfileMediaAccess $access,
        private readonly ProfileImageLimit $imageLimit,
        private readonly ProfileVideoLimit $videoLimit,
        private readonly PolicyAcceptanceService $policies,
    ) {}

    public function index(Profile $profile): View
    {
        abort_unless($this->access->canView(request()->user(), $profile), 403);

        return view('onboarding.media', [
            'profile' => $profile->load('images', 'videos'),
            'limit' => $this->imageLimit->for($profile),
            'videoLimit' => $this->videoLimit->for($profile),
            'canManage' => $this->access->canManage(request()->user(), $profile),
            'requiredPolicies' => $this->policies->outstanding('media_submission', request()->user(), $profile),
        ]);
    }

    public function store(StoreProfileImageRequest $request, Profile $profile): RedirectResponse|JsonResponse
    {
        $file = $request->file('image');
        $dimensions = @getimagesize($file->getRealPath());
        if (! $dimensions || empty($dimensions[0]) || empty($dimensions[1])) {
            return $this->mediaError($request, 'image', 'The file could not be read as an image. Please upload a standard JPEG, PNG or WebP.');
        }

        $accepted = $this->policies->acceptedSelection(
            'media_submission',
            $request->validated('policy_acceptances', []),
            $request->user(),
            $profile,
        );

        try {
            $image = DB::transaction(function () use ($request, $profile, $file, $dimensions, $accepted): ProfileImage {
                $profile = Profile::query()->lockForUpdate()->findOrFail($profile->id);
                $limit = $this->imageLimit->for($profile);
                $currentCount = $profile->images()->whereNotIn('status', ['rejected', 'private'])->count();
                abort_if($limit < 1 || $currentCount >= $limit, 422, 'The package photo limit has been reached.');

                $hash = hash_file('sha256', $file->getRealPath());
                abort_if($profile->images()->where('exact_hash', $hash)->exists(), 422, 'This photo has already been uploaded to the profile.');

                $publicId = (string) Str::uuid();
                $quarantinePath = $profile->public_id.'/'.$publicId.'.upload';
                Storage::disk('quarantine')->putFileAs($profile->public_id, $file, $publicId.'.upload');

                $image = $profile->images()->create([
                    'public_id' => $publicId,
                    'storage_directory' => $quarantinePath,
                    'sort_order' => ($profile->images()->max('sort_order') ?? 0) + 10,
                    'status' => 'quarantined',
                    'width' => $dimensions[0],
                    'height' => $dimensions[1],
                    'aspect_ratio' => $dimensions[0] / $dimensions[1],
                    'mime_type' => $dimensions['mime'] ?? $file->getMimeType() ?? 'application/octet-stream',
                    'file_size' => $file->getSize(),
                    'exact_hash' => $hash,
                ]);
                $this->policies->record($request->user(), 'media_submission', $accepted, $request, $profile);

                return $image;
            });
        } catch (HttpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return $this->mediaError($request, 'image', 'The photo could not be stored. Please try again in a moment.');
        }

        ProcessProfileImage::dispatch($image->id)->afterCommit();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'Photo uploaded securely and queued for processing.']);
        }

        return back()->with('status', 'Photo uploaded securely and queued for processing.');
    }

    public function retry(Profile $profile, ProfileImage $image): RedirectResponse
    {
        abort_unless($image->profile_id === $profile->id, 404);
        abort_unless($this->access->canManage(request()->user(), $profile), 403);

        $stuck = $image->status === 'processing' && $image->updated_at?->lt(now()->subMinutes(15));
        abort_unless($image->status === 'rejected' || $stuck, 409, 'Only a failed photo can be retried.');

        if (! Storage::disk('quarantine')->exists($profile->public_id.'/'.$image->public_id.'.upload')) {
            return back()->withErrors(['image' => 'The original upload is no longer available. Please upload the photo again.']);
        }

        $image->update(['status' => 'quarantined', 'processing_error' => null]);
        ProcessProfileImage::dispatch($image->id)->afterCommit();

        return back()->with('status', 'Photo re-queued for processing.');
    }

    private function mediaError(Request $request, string $key, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => [$key => [$message]]], 422);
        }

        return back()->withErrors([$key => $message]);
    }

    public function storeVideo(StoreProfileVideoRequest $request, Profile $profile): RedirectResponse|JsonResponse
    {
        $file = $request->file('video');
        $accepted = $this->policies->acceptedSelection(
            'media_submission',
            $request->validated('policy_acceptances', []),
            $request->user(),
            $profile,
        );

        try {
            $video = DB::transaction(function () use ($request, $profile, $file, $accepted): ProfileVideo {
                $profile = Profile::query()->lockForUpdate()->findOrFail($profile->id);
                $limit = $this->videoLimit->for($profile);
                $currentCount = $profile->videos()->whereNotIn('status', ['rejected', 'private'])->count();
                abort_if($limit < 1 || $currentCount >= $limit, 422, 'The package video limit has been reached.');

                $hash = hash_file('sha256', $file->getRealPath());
                abort_if($profile->videos()->where('exact_hash', $hash)->exists(), 422, 'This video has already been uploaded to the profile.');

                $publicId = (string) Str::uuid();
                $extension = strtolower($file->getClientOriginalExtension() ?: 'mp4');
                Storage::disk('quarantine')->putFileAs('videos/'.$profile->public_id, $file, $publicId.'.upload');

                $video = $profile->videos()->create([
                    'public_id' => $publicId,
                    'storage_directory' => 'videos/'.$profile->public_id.'/'.$publicId.'.upload',
                    'sort_order' => ($profile->videos()->max('sort_order') ?? 0) + 10,
                    'status' => 'quarantined',
                    'mime_type' => $file->getMimeType() ?? 'video/mp4',
                    'file_size' => $file->getSize(),
                    'file_extension' => in_array($extension, ['mp4', 'm4v', 'webm', 'mov'], true) ? $extension : 'mp4',
                    'exact_hash' => $hash,
                ]);
                $this->policies->record($request->user(), 'media_submission', $accepted, $request, $profile);

                return $video;
            });
        } catch (HttpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return $this->mediaError($request, 'video', 'The video could not be stored. Please try again in a moment.');
        }

        ProcessProfileVideo::dispatch($video->id)->afterCommit();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'Video uploaded securely and queued for processing.']);
        }

        return back()->with('status', 'Video uploaded securely and queued for processing.');
    }

    public function previewVideo(Profile $profile, ProfileVideo $video): BinaryFileResponse
    {
        abort_unless($video->profile_id === $profile->id, 404);
        abort_unless($this->access->canView(request()->user(), $profile), 403);
        abort_unless(in_array($video->status, ['pending_review', 'approved'], true), 404);

        $disk = $video->status === 'approved' ? Storage::disk('profile_media') : Storage::disk('media_review');
        $path = $video->storage_directory.'/'.$video->sourceFilename();
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => $video->mime_type ?: 'video/mp4',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function retryVideo(Profile $profile, ProfileVideo $video): RedirectResponse
    {
        abort_unless($video->profile_id === $profile->id, 404);
        abort_unless($this->access->canManage(request()->user(), $profile), 403);

        $stuck = $video->status === 'processing' && $video->updated_at?->lt(now()->subMinutes(15));
        abort_unless($video->status === 'rejected' || $stuck, 409, 'Only a failed video can be retried.');

        if (! Storage::disk('quarantine')->exists('videos/'.$profile->public_id.'/'.$video->public_id.'.upload')) {
            return back()->withErrors(['video' => 'The original upload is no longer available. Please upload the video again.']);
        }

        $video->update(['status' => 'quarantined', 'processing_error' => null]);
        ProcessProfileVideo::dispatch($video->id)->afterCommit();

        return back()->with('status', 'Video re-queued for processing.');
    }

    public function destroyVideo(Profile $profile, ProfileVideo $video): RedirectResponse
    {
        abort_unless($video->profile_id === $profile->id, 404);
        abort_unless($this->access->canManage(request()->user(), $profile), 403);
        abort_if($video->status === 'processing', 409, 'Wait for video processing to finish before removing it.');

        match ($video->status) {
            'quarantined', 'rejected' => Storage::disk('quarantine')->delete('videos/'.$profile->public_id.'/'.$video->public_id.'.upload'),
            'pending_review' => Storage::disk('media_review')->deleteDirectory($video->storage_directory),
            'approved' => Storage::disk('profile_media')->deleteDirectory($video->storage_directory),
            default => null,
        };

        $video->delete();

        return back()->with('status', 'Video removed.');
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
        abort_if($image->status === 'processing', 409, 'Wait for photo processing to finish before removing it.');

        match ($image->status) {
            'quarantined', 'rejected' => Storage::disk('quarantine')->delete($profile->public_id.'/'.$image->public_id.'.upload'),
            'pending_review' => Storage::disk('media_review')->deleteDirectory($image->storage_directory),
            'approved' => Storage::disk('profile_media')->deleteDirectory($image->storage_directory),
            default => null,
        };

        $image->delete();

        return back()->with('status', 'Photo removed.');
    }
}
