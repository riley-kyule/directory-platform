<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePolicyVersionRequest;
use App\Models\AuditLog;
use App\Models\PolicyVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PolicyManagementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('policies.manage');

        return view('admin.policies.index', [
            'policyTypes' => collect(PolicyVersion::TYPES)->map(function (string $label, string $type): array {
                return [
                    'type' => $type,
                    'label' => $label,
                    'published' => PolicyVersion::query()->where('policy_type', $type)->published()->latest('published_at')->first(),
                    'draft' => PolicyVersion::query()->where('policy_type', $type)->whereNull('published_at')->latest('id')->first(),
                ];
            }),
        ]);
    }

    public function edit(string $policyType): View
    {
        Gate::authorize('policies.manage');
        $this->validateType($policyType);

        return view('admin.policies.edit', [
            'policyType' => $policyType,
            'label' => PolicyVersion::TYPES[$policyType],
            'draft' => PolicyVersion::query()
                ->where('policy_type', $policyType)
                ->whereNull('published_at')
                ->latest('id')
                ->first(),
            'published' => PolicyVersion::query()
                ->where('policy_type', $policyType)
                ->published()
                ->latest('published_at')
                ->first(),
        ]);
    }

    public function save(SavePolicyVersionRequest $request, string $policyType): RedirectResponse
    {
        $this->validateType($policyType);
        $validated = $request->validated();

        $policy = DB::transaction(function () use ($request, $policyType, $validated): PolicyVersion {
            PolicyVersion::query()->where('policy_type', $policyType)->lockForUpdate()->get();
            $policy = PolicyVersion::query()
                ->where('policy_type', $policyType)
                ->whereNull('published_at')
                ->latest('id')
                ->first() ?? new PolicyVersion(['policy_type' => $policyType]);
            $previous = $policy->exists ? $this->auditState($policy) : [];
            $policy->fill([
                'version' => $validated['version'],
                'title' => $validated['title'],
                'summary' => $validated['summary'] ?? null,
                'content' => $validated['content'],
                'content_hash' => hash('sha256', $validated['content']),
                'requires_reacceptance' => $validated['requires_reacceptance'],
                'created_by' => $policy->created_by ?? $request->user()->id,
            ]);

            if ($validated['action'] === 'publish') {
                $policy->published_at = now();
                $policy->published_by = $request->user()->id;
            }
            $policy->save();

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => $validated['action'] === 'publish' ? 'policies.publish' : 'policies.save-draft',
                'target_type' => 'policy-version',
                'target_id' => $policy->id,
                'previous_state' => $previous,
                'new_state' => $this->auditState($policy),
                'ip_address' => $request->ip(),
                'user_agent' => str($request->userAgent())->limit(500)->toString(),
            ]);

            return $policy;
        });

        $message = $policy->published_at ? 'Policy version published.' : 'Policy draft saved.';

        return redirect()->route('admin.policies.index')->with('status', $message);
    }

    private function validateType(string $policyType): void
    {
        abort_unless(array_key_exists($policyType, PolicyVersion::TYPES), 404);
    }

    /** @return array<string, mixed> */
    private function auditState(PolicyVersion $policy): array
    {
        return [
            'policy_type' => $policy->policy_type,
            'version' => $policy->version,
            'title' => $policy->title,
            'content_hash' => $policy->content_hash,
            'requires_reacceptance' => $policy->requires_reacceptance,
            'published_at' => $policy->published_at?->toIso8601String(),
        ];
    }
}
