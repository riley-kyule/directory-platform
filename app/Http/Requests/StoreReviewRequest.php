<?php

namespace App\Http\Requests;

use App\Models\Profile;
use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return $profile instanceof Profile
            && Profile::query()->publiclyVisible()->whereKey($profile->id)->exists();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) ($this->user()?->email ?? $this->input('email'))))]);
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:20', 'max:2000'],
            'reviewer_name' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'website' => ['prohibited'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $profile = $this->route('profile');
            if (! $profile instanceof Profile) {
                return;
            }

            $email = (string) $this->input('email');
            $userId = $this->user()?->id;
            $ownedByAccount = $userId && (
                $profile->owner_user_id === $userId
                || $profile->currentAgency()->where('agencies.owner_user_id', $userId)->exists()
            );
            $ownerEmails = collect([$profile->owner?->email])
                ->concat($profile->currentAgency()->with('owner:id,email')->get()->pluck('owner.email'))
                ->filter()
                ->map(fn (string $ownerEmail) => strtolower($ownerEmail));

            if ($ownedByAccount || $ownerEmails->contains($email)) {
                $validator->errors()->add('email', 'A profile owner or agency cannot review its own listing.');

                return;
            }

            if (Review::duplicateExists($profile->id, hash('sha256', $email))) {
                $validator->errors()->add('email', 'A recent review from this email is already pending or published for this profile.');
            }
        }];
    }
}
