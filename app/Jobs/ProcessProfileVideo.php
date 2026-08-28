<?php

namespace App\Jobs;

use App\Models\ProfileVideo;
use App\Services\DirectorySettings;
use App\Services\ProfileImageVisibility;
use App\Support\MediaFilesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * "Validate and store" pipeline for uploaded profile videos. There is no
 * transcoding: the upload is strictly type-checked, moved out of quarantine into
 * the private review area, and (only if an ffmpeg/ffprobe binary is configured)
 * probed for dimensions/duration and given a poster frame. Anything that fails
 * the header/MIME checks is rejected with a visible reason.
 */
class ProcessProfileVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public function __construct(public readonly int $profileVideoId)
    {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $video = ProfileVideo::withTrashed()->with('profile')->find($this->profileVideoId);
        if (! $video || $video->trashed() || ! in_array($video->status, ['quarantined', 'rejected'], true)) {
            return;
        }

        $quarantineDisk = Storage::disk('quarantine');
        $quarantinePath = $this->quarantinePath($video);
        if (! $quarantineDisk->exists($quarantinePath)) {
            $video->update([
                'status' => 'rejected',
                'processing_error' => 'The uploaded file is no longer available. Please upload it again.',
            ]);

            return;
        }

        $video->update(['status' => 'processing', 'processing_error' => null]);
        $sourcePath = $quarantineDisk->path($quarantinePath);
        $settings = app(DirectorySettings::class);

        $this->validateContainer($sourcePath, $settings);

        $probe = $this->probe($sourcePath, $settings);
        if ($probe['duration'] !== null && $probe['duration'] > $settings->integer('media.video_max_duration_seconds')) {
            throw new RuntimeException('The video is longer than the maximum allowed duration.');
        }

        $finalDirectory = 'videos/'.substr($video->public_id, 0, 2).'/'.substr($video->public_id, 2, 2).'/'.$video->public_id;
        $stagingDisk = Storage::disk('media_staging');
        $reviewDisk = Storage::disk('media_review');
        $stagingDirectory = 'videos/'.$video->public_id.'-'.Str::lower(Str::random(10));
        $stagingDisk->makeDirectory($stagingDirectory);

        try {
            MediaFilesystem::moveFile($sourcePath, $stagingDisk->path($stagingDirectory.'/'.$video->sourceFilename()));

            $hasPoster = $this->makePoster(
                $stagingDisk->path($stagingDirectory.'/'.$video->sourceFilename()),
                $stagingDisk->path($stagingDirectory.'/poster.jpg'),
                $settings,
            );

            $finalPath = $reviewDisk->path($finalDirectory);
            if (is_dir($finalPath)) {
                $reviewDisk->deleteDirectory($finalDirectory);
            }
            MediaFilesystem::moveDirectory($stagingDisk->path($stagingDirectory), $finalPath);

            $video->update([
                'storage_directory' => $finalDirectory,
                'status' => 'pending_review',
                'width' => $probe['width'],
                'height' => $probe['height'],
                'duration_seconds' => $probe['duration'],
                'has_poster' => $hasPoster,
                'processing_error' => null,
            ]);
        } finally {
            $stagingDisk->deleteDirectory($stagingDirectory);
        }

        // No moderation hold: on a live profile the video goes public as soon as
        // it is processed; on a draft it waits for the profile to be activated.
        if ($video->profile && $video->profile->status->isPublic()) {
            app(ProfileImageVisibility::class)->publishVideos($video->profile);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $video = ProfileVideo::query()->with('profile')->find($this->profileVideoId);
        if (! $video) {
            return;
        }

        Storage::disk('quarantine')->delete($this->quarantinePath($video));
        $video->update([
            'status' => 'rejected',
            'processing_error' => Str::limit($exception?->getMessage() ?? 'Video processing failed.', 1000),
        ]);
    }

    private function quarantinePath(ProfileVideo $video): string
    {
        return 'videos/'.($video->profile?->public_id ?? 'orphan').'/'.$video->public_id.'.upload';
    }

    private function validateContainer(string $path, DirectorySettings $settings): void
    {
        if ((filesize($path) ?: 0) > $settings->integer('media.video_max_kilobytes') * 1024) {
            throw new RuntimeException('The video exceeds the maximum allowed file size.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (! in_array($mime, config('directory.media.accepted_video_mime_types'), true)) {
            throw new RuntimeException('The file is not a supported video format (MP4, WebM or MOV).');
        }

        $handle = fopen($path, 'rb');
        $head = $handle ? (string) fread($handle, 32) : '';
        if ($handle) {
            fclose($handle);
        }

        $isMp4 = substr($head, 4, 4) === 'ftyp';
        $isWebm = str_starts_with($head, "\x1A\x45\xDF\xA3");
        if (! $isMp4 && ! $isWebm) {
            throw new RuntimeException('The video file header is not a valid MP4/MOV or WebM stream.');
        }
    }

    /**
     * @return array{width: int|null, height: int|null, duration: int|null}
     */
    private function probe(string $path, DirectorySettings $settings): array
    {
        $binary = $settings->string('media.ffprobe_path');
        $empty = ['width' => null, 'height' => null, 'duration' => null];
        if ($binary === '' || ! is_executable($binary)) {
            return $empty;
        }

        try {
            $process = new Process([
                $binary, '-v', 'error', '-select_streams', 'v:0',
                '-show_entries', 'stream=width,height:format=duration',
                '-of', 'json', $path,
            ]);
            $process->setTimeout(30);
            $process->run();
            if (! $process->isSuccessful()) {
                return $empty;
            }

            $data = json_decode($process->getOutput(), true);
            $stream = $data['streams'][0] ?? [];

            return [
                'width' => isset($stream['width']) ? (int) $stream['width'] : null,
                'height' => isset($stream['height']) ? (int) $stream['height'] : null,
                'duration' => isset($data['format']['duration']) ? (int) round((float) $data['format']['duration']) : null,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    private function makePoster(string $videoPath, string $posterPath, DirectorySettings $settings): bool
    {
        $binary = $settings->string('media.ffmpeg_path');
        if ($binary === '' || ! is_executable($binary)) {
            return false;
        }

        try {
            $process = new Process([
                $binary, '-y', '-ss', '1', '-i', $videoPath,
                '-frames:v', '1', '-vf', 'scale=640:-2', $posterPath,
            ]);
            $process->setTimeout(45);
            $process->run();

            return $process->isSuccessful() && is_file($posterPath);
        } catch (Throwable) {
            return false;
        }
    }
}
