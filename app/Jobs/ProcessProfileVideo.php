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
 * Fail-closed video pipeline: validate and probe the upload, transcode it into a
 * canonical metadata-free MP4, create a poster, then place it on the private
 * staging disk. Media for a live profile is published automatically as soon as
 * processing succeeds.
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
            $stagedSource = $stagingDisk->path($stagingDirectory.'/'.$video->sourceFilename());
            MediaFilesystem::moveFile($sourcePath, $stagedSource);
            $canonicalSource = $this->transcode($stagedSource, $stagingDisk->path($stagingDirectory), $settings);
            $canonicalProbe = $this->probe($canonicalSource, $settings);
            $canonicalSize = filesize($canonicalSource) ?: $video->file_size;

            $hasPoster = $this->makePoster(
                $canonicalSource,
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
                'mime_type' => 'video/mp4',
                'file_extension' => 'mp4',
                'file_size' => $canonicalSize,
                'width' => $canonicalProbe['width'],
                'height' => $canonicalProbe['height'],
                'duration_seconds' => $canonicalProbe['duration'],
                'has_poster' => $hasPoster,
                'processing_error' => null,
            ]);
        } finally {
            $stagingDisk->deleteDirectory($stagingDirectory);
        }

        if ($video->profile?->status->isPublic()) {
            try {
                app(ProfileImageVisibility::class)->publishVideos($video->profile);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $video = ProfileVideo::query()->with('profile')->find($this->profileVideoId);
        if (! $video) {
            return;
        }

        if (in_array($video->status, ['pending_review', 'reviewed', 'approved'], true)) {
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
            if (app()->environment('testing')) {
                return $empty;
            }

            throw new RuntimeException('Video processing is unavailable because ffprobe is not configured.');
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
                throw new RuntimeException('The video could not be safely inspected.');
            }

            $data = json_decode($process->getOutput(), true);
            $stream = $data['streams'][0] ?? [];

            $result = [
                'width' => isset($stream['width']) ? (int) $stream['width'] : null,
                'height' => isset($stream['height']) ? (int) $stream['height'] : null,
                'duration' => isset($data['format']['duration']) ? (int) round((float) $data['format']['duration']) : null,
            ];
            if (! app()->environment('testing') && (! $result['width'] || ! $result['height'] || ! $result['duration'])) {
                throw new RuntimeException('The video is missing required stream metadata.');
            }

            return $result;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('The video could not be safely inspected.');
        }
    }

    private function transcode(string $sourcePath, string $stagingDirectory, DirectorySettings $settings): string
    {
        $binary = $settings->string('media.ffmpeg_path');
        if ($binary === '' || ! is_executable($binary)) {
            if (app()->environment('testing')) {
                return $sourcePath;
            }

            throw new RuntimeException('Video processing is unavailable because ffmpeg is not configured.');
        }

        $encodedPath = $stagingDirectory.'/encoded.mp4';
        $process = new Process([
            $binary, '-y', '-v', 'error', '-i', $sourcePath,
            '-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '-1', '-map_chapters', '-1',
            '-vf', "scale='min(1920,iw)':-2:force_original_aspect_ratio=decrease,fps=30",
            '-c:v', 'libx264', '-preset', 'medium', '-crf', '23', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', $encodedPath,
        ]);
        $process->setTimeout(240);
        $process->run();
        if (! $process->isSuccessful() || ! is_file($encodedPath) || filesize($encodedPath) === 0) {
            throw new RuntimeException('The video could not be transcoded into the safe delivery format.');
        }

        $canonicalPath = $stagingDirectory.'/source.mp4';
        if (is_file($sourcePath)) {
            unlink($sourcePath);
        }
        if (! rename($encodedPath, $canonicalPath)) {
            throw new RuntimeException('The transcoded video could not be finalized.');
        }

        return $canonicalPath;
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
