<?php

namespace App\Http\Requests;

use App\Models\Profile;
use App\Services\DirectorySettings;
use App\Services\ProfileMediaAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreProfileVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return $profile instanceof Profile
            && app(ProfileMediaAccess::class)->canUpload($this->user(), $profile);
    }

    public function rules(): array
    {
        $settings = app(DirectorySettings::class);

        return [
            'video' => [
                'required',
                'file',
                'mimes:mp4,m4v,webm,mov',
                'mimetypes:'.implode(',', config('directory.media.accepted_video_mime_types')),
                'max:'.$settings->integer('media.video_max_kilobytes'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'video.mimes' => 'The video must be an MP4, WebM or MOV file.',
            'video.mimetypes' => 'The video must be an MP4, WebM or MOV file.',
            'video.max' => 'The video exceeds the maximum allowed file size.',
        ];
    }
}
