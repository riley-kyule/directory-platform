<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use App\Enums\ProviderType;
use App\Models\Location;
use App\Models\TaxonomyOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProfileOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user?->account_type !== AccountType::Provider) {
            return false;
        }

        if ($user->provider_type === ProviderType::Independent) {
            return $user->profile === null;
        }

        return $user->provider_type === ProviderType::Agency
            && $user->agency !== null
            && $user->agency->profiles()->wherePivotNull('unassigned_at')->count() < config('directory.agency_profile_limit');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => preg_replace('/[^0-9+]/', '', (string) $this->input('phone')),
            'rate_currency' => $this->filled('rate_currency') ? strtoupper((string) $this->input('rate_currency')) : null,
            'allows_incall' => $this->boolean('allows_incall'),
            'allows_outcall' => $this->boolean('allows_outcall'),
            'whatsapp_enabled' => $this->boolean('whatsapp_enabled'),
            'telegram_phone_enabled' => $this->boolean('telegram_phone_enabled'),
            'smoker' => $this->filled('smoker') ? $this->boolean('smoker') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['required', 'string', 'min:40', 'max:10000'],
            'phone' => ['required', 'regex:/^\+?[1-9][0-9]{7,14}$/'],
            'whatsapp_enabled' => ['boolean'],
            'telegram_phone_enabled' => ['boolean'],
            'telegram_username' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9_]{5,32}$/'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'instagram_handle' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9._]{1,30}$/'],
            'snapchat_handle' => ['nullable', 'string', 'max:64'],
            'tiktok_handle' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9._]{1,30}$/'],
            'primary_location_id' => ['required', Rule::exists('locations', 'id')->where('status', 'published')],
            'sublocation_id' => ['required', Rule::exists('locations', 'id')->where('status', 'published')],
            'gender_option_id' => ['required', $this->taxonomyRule('gender')],
            'date_of_birth' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'ethnicity_option_id' => ['required', $this->taxonomyRule('ethnicity')],
            'build_option_id' => ['required', $this->taxonomyRule('build')],
            'bust_size_option_id' => ['nullable', $this->taxonomyRule('bust_size')],
            'allows_incall' => ['boolean'],
            'allows_outcall' => ['boolean'],
            'hair_color_option_id' => ['nullable', $this->taxonomyRule('hair_color')],
            'hair_length_option_id' => ['nullable', $this->taxonomyRule('hair_length')],
            'height_cm' => ['nullable', 'integer', 'between:100,250'],
            'weight_kg' => ['nullable', 'numeric', 'between:30,300'],
            'smoker' => ['nullable', 'boolean'],
            'sexual_orientation_option_id' => ['nullable', $this->taxonomyRule('sexual_orientation')],
            'language_ids' => ['nullable', 'array'],
            'language_ids.*' => [$this->taxonomyRule('language')],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => [$this->taxonomyRule('service')],
            'requested_package_id' => ['required', Rule::exists('packages', 'id')->where('is_active', true)],
            'rate_currency' => ['nullable', 'string', 'size:3', 'uppercase'],
            'rate_period_option_id' => ['nullable', 'required_with:rate_price,rate_currency', $this->taxonomyRule('rate_period')],
            'rate_price' => ['nullable', 'required_with:rate_currency,rate_period_option_id', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->boolean('allows_incall') && ! $this->boolean('allows_outcall')) {
                $validator->errors()->add('availability', 'Choose Incall, Outcall, or both.');
            }

            if ($this->filled('primary_location_id') && $this->filled('sublocation_id')) {
                $isChild = Location::query()
                    ->whereKey($this->integer('sublocation_id'))
                    ->where('parent_id', $this->integer('primary_location_id'))
                    ->exists();

                if (! $isChild) {
                    $validator->errors()->add('sublocation_id', 'Choose a sub-location within the selected location.');
                }
            }

            $gender = TaxonomyOption::query()->find($this->integer('gender_option_id'));
            if (($gender?->settings['requires_bust_size'] ?? false) && ! $this->filled('bust_size_option_id')) {
                $validator->errors()->add('bust_size_option_id', 'Bust size is required for the selected gender.');
            }
        }];
    }

    private function taxonomyRule(string $type): mixed
    {
        return Rule::exists('taxonomy_options', 'id')
            ->where(fn ($query) => $query->where('type', $type)->where('is_active', true));
    }
}
