<?php

namespace App\Http\Requests;

use App\Models\Agency;
use App\Models\DirectoryRedirect;
use App\Models\LocationContent;
use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDirectoryRedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('seo.redirects') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_path' => $this->normalizePath($this->input('source_path')),
            'target_path' => $this->normalizePath($this->input('target_path')),
        ]);
    }

    public function rules(): array
    {
        $isGone = (int) $this->input('status_code') === 410;

        return [
            'source_path' => [
                'required', 'string', 'max:255', 'regex:/^\/[a-z0-9][a-z0-9\/._~-]*$/',
                Rule::unique('redirects', 'source_path'),
            ],
            'target_path' => [
                Rule::requiredIf(! $isGone), Rule::prohibitedIf($isGone),
                'nullable', 'string', 'max:255', 'regex:/^\/[a-z0-9][a-z0-9\/._~-]*$/',
                'different:source_path',
            ],
            'status_code' => ['required', 'integer', Rule::in([301, 302, 307, 308, 410])],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $source = $this->string('source_path')->toString();
            if ($this->isCanonicalPath($source)) {
                $validator->errors()->add('source_path', 'A live canonical URL cannot be used as a redirect source.');
            }

            $target = $this->string('target_path')->toString();
            $visited = [$source];
            for ($depth = 0; $target && $depth < 20; $depth++) {
                if (in_array($target, $visited, true)) {
                    $validator->errors()->add('target_path', 'This redirect would create a loop.');

                    break;
                }
                $visited[] = $target;
                $next = DirectoryRedirect::query()
                    ->where('source_path', $target)
                    ->where('is_active', true)
                    ->first();
                if (! $next || $next->status_code === 410) {
                    break;
                }
                $target = $next->target_path;
            }
        }];
    }

    private function normalizePath(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return '/'.ltrim(strtolower(trim($value)), '/');
    }

    private function isCanonicalPath(string $path): bool
    {
        if (str_starts_with($path, '/escort/')) {
            return Profile::query()->where('slug', str($path)->after('/escort/')->toString())->exists();
        }
        if (str_starts_with($path, '/agency/')) {
            return Agency::query()->where('slug', str($path)->after('/agency/')->toString())->exists();
        }

        return LocationContent::query()->where('canonical_path', $path)->exists();
    }
}
