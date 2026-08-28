<?php

namespace App\Http\Requests;

use App\Models\Profile;
use App\Services\DirectorySettings;
use App\Services\PolicyAcceptanceService;
use App\Services\ProfileMediaAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProfileVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return $profile instanceof Profile
            && app(ProfileMediaAccess::class)->canManage($this->user(), $profile);
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
            'policy_acceptances' => ['nullable', 'array'],
            'policy_acceptances.*' => ['integer'],
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

    public function after(): array
    {
        return [function (Validator $validator): void {
            $profile = $this->route('profile');
            if ($profile instanceof Profile && ! app(PolicyAcceptanceService::class)->allRequiredSelected(
                'media_submission',
                $this->input('policy_acceptances', []),
                $this->user(),
                $profile,
            )) {
                $validator->errors()->add('policy_acceptances', 'Accept the current media policy before uploading.');
            }
        }];
    }
}
