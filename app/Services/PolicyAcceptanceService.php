<?php

namespace App\Services;

use App\Enums\ProviderType;
use App\Models\PolicyAcceptance;
use App\Models\PolicyVersion;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PolicyAcceptanceService
{
    /** @return Collection<string, PolicyVersion> */
    public function latestPublished(): Collection
    {
        $ids = Cache::rememberForever(PolicyVersion::CACHE_KEY, fn () => PolicyVersion::query()
            ->published()
            ->latest('published_at')
            ->latest('id')
            ->get(['id', 'policy_type'])
            ->unique('policy_type')
            ->mapWithKeys(fn (PolicyVersion $policy) => [$policy->policy_type => $policy->id])
            ->all());

        return PolicyVersion::query()
            ->whereIn('id', array_values($ids))
            ->get()
            ->keyBy('policy_type');
    }

    /** @return Collection<int, PolicyVersion> */
    public function outstanding(string $action, ?User $user = null, ?Profile $profile = null): Collection
    {
        $requiredTypes = $this->typesFor($action, $user, $profile);
        $policies = $this->latestPublished()
            ->filter(fn (PolicyVersion $policy, string $type): bool => in_array($type, $requiredTypes, true))
            ->values();
        if (! $user) {
            return $policies;
        }

        return $policies->reject(function (PolicyVersion $policy) use ($user): bool {
            if ($user->policyAcceptances()->where('policy_version_id', $policy->id)->exists()) {
                return true;
            }

            return ! $policy->requires_reacceptance
                && $user->policyAcceptances()
                    ->whereHas('policyVersion', fn ($query) => $query
                        ->where('policy_type', $policy->policy_type)
                        ->whereNotNull('published_at'))
                    ->exists();
        })->values();
    }

    /** @param array<int, int|string> $selectedIds
     * @return Collection<int, PolicyVersion>
     */
    public function acceptedSelection(
        string $action,
        array $selectedIds,
        ?User $user = null,
        ?Profile $profile = null,
    ): Collection {
        $outstanding = $this->outstanding($action, $user, $profile);
        $selected = collect($selectedIds)->map(fn ($id) => (int) $id);

        return $outstanding->filter(fn (PolicyVersion $policy) => $selected->contains($policy->id))->values();
    }

    /** @param array<int, int|string> $selectedIds */
    public function allRequiredSelected(
        string $action,
        array $selectedIds,
        ?User $user = null,
        ?Profile $profile = null,
    ): bool {
        $outstanding = $this->outstanding($action, $user, $profile);

        return $this->acceptedSelection($action, $selectedIds, $user, $profile)->count() === $outstanding->count();
    }

    /** @param Collection<int, PolicyVersion> $policies */
    public function record(
        User $user,
        string $action,
        Collection $policies,
        Request $request,
        ?Profile $profile = null,
    ): void {
        foreach ($policies as $policy) {
            PolicyAcceptance::query()->create([
                'policy_version_id' => $policy->id,
                'user_id' => $user->id,
                'profile_id' => $profile?->id,
                'action' => $action,
                'accepted_at' => now(),
                'request_context' => [
                    'ip_address' => $request->ip(),
                    'user_agent' => str($request->userAgent())->limit(500)->toString(),
                    'route' => $request->route()?->getName(),
                ],
            ]);
        }
    }

    /** @return list<string> */
    private function typesFor(string $action, ?User $user, ?Profile $profile): array
    {
        if ($action === 'registration') {
            return ['terms', 'privacy'];
        }

        if ($action === 'media_submission') {
            return ['media'];
        }

        $types = ['provider'];
        $isAgency = $user?->provider_type === ProviderType::Agency
            || $profile?->currentAgency()->exists();

        if ($isAgency) {
            $types[] = 'agency';
        }

        return $types;
    }
}
