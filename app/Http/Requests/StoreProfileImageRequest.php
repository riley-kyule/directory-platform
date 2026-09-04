<?php

namespace App\Http\Requests;

use App\Models\Profile;
use App\Services\DirectorySettings;
use App\Services\PolicyAcceptanceService;
use App\Services\ProfileMediaAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProfileImageRequest extends FormRequest
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
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.$settings->integer('media.maximum_file_kilobytes'),
                // Dimension bounds are enforced in after() so the error can name
                // the actual size and the limit instead of "invalid dimensions".
            ],
            'policy_acceptances' => ['nullable', 'array'],
            'policy_acceptances.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        $extension = strtolower((string) $this->file('image')?->getClientOriginalExtension());
        if (in_array($extension, ['heic', 'heif'], true)) {
            $heic = "iPhone HEIC photos aren't supported yet. On your iPhone open Settings → Camera → Formats and choose \"Most Compatible\", then take the photo again — or share it as a JPEG.";

            return ['image.mimes' => $heic, 'image.mimetypes' => $heic];
        }

        return [
            'image.mimes' => 'The photo must be a JPEG, PNG or WebP file.',
            'image.mimetypes' => 'The photo must be a JPEG, PNG or WebP file.',
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

            $minWidth = $settings->integer('media.minimum_width');
            $minHeight = $settings->integer('media.minimum_height');
            $maxDimension = $settings->integer('media.maximum_dimension');

            if ($width < $minWidth || $height < $minHeight) {
                $validator->errors()->add('image', "This photo is {$width}×{$height}px. Please upload one at least {$minWidth}×{$minHeight}px — closer to portrait orientation works best.");
            } elseif ($width > $maxDimension || $height > $maxDimension) {
                $validator->errors()->add('image', "This photo is {$width}×{$height}px, larger than the {$maxDimension}px limit on a side. Resize it and upload again.");
            } elseif ($width * $height > $settings->integer('media.maximum_pixels')) {
                $megapixels = round($settings->integer('media.maximum_pixels') / 1_000_000);
                $validator->errors()->add('image', "This photo has too many pixels (limit is {$megapixels} megapixels). Resize it and upload again.");
            }

            $ratio = $width / $height;
            if ($ratio < $settings->float('media.minimum_aspect_ratio') || $ratio > $settings->float('media.maximum_aspect_ratio')) {
                $validator->errors()->add('image', 'This photo is too tall or too wide — crop it closer to a standard portrait or landscape shape.');
            }
        }];
    }
}
