<?php

namespace App\Http\Requests;

use App\Models\Profile;
use App\Services\DirectorySettings;
use App\Services\ProfileMediaAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Validator;

class StoreProfileImageRequest extends FormRequest
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
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.$settings->integer('media.maximum_file_kilobytes'),
                (new Dimensions)
                    ->minWidth($settings->integer('media.minimum_width'))
                    ->minHeight($settings->integer('media.minimum_height'))
                    ->maxWidth($settings->integer('media.maximum_dimension'))
                    ->maxHeight($settings->integer('media.maximum_dimension')),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $file = $this->file('image');
            if (! $file || ! $file->isValid()) {
                return;
            }

            $dimensions = @getimagesize($file->getRealPath());
            if (! $dimensions) {
                $validator->errors()->add('image', 'The uploaded file could not be decoded as an image.');

                return;
            }

            [$width, $height] = $dimensions;
            $settings = app(DirectorySettings::class);
            if ($width * $height > $settings->integer('media.maximum_pixels')) {
                $validator->errors()->add('image', 'The decoded image contains too many pixels.');
            }

            $ratio = $width / $height;
            if ($ratio < $settings->float('media.minimum_aspect_ratio') || $ratio > $settings->float('media.maximum_aspect_ratio')) {
                $validator->errors()->add('image', 'The image aspect ratio is outside the allowed range.');
            }
        }];
    }
}
