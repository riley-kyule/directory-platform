<?php

namespace App\Http\Requests;

use App\Models\Profile;
use App\Services\ProfileMediaAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreModerationAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return $profile instanceof Profile
            && app(ProfileMediaAccess::class)->owns($this->user(), $profile);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:30', 'max:5000']];
    }
}
