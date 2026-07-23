<?php

namespace App\Http\Controllers;

use App\Models\PolicyVersion;
use Illuminate\View\View;

class PolicyPageController extends Controller
{
    public function show(string $policyType): View
    {
        abort_unless(array_key_exists($policyType, PolicyVersion::TYPES), 404);
        $policy = PolicyVersion::query()
            ->where('policy_type', $policyType)
            ->published()
            ->latest('published_at')
            ->firstOrFail();

        return view('policies.show', [
            'policy' => $policy,
            'metaTitle' => $policy->title.' — '.config('app.name'),
            'metaDescription' => $policy->summary ?: str($policy->content)->squish()->limit(155),
            'canonicalUrl' => $policy->publicRoute(),
            'robots' => 'index,follow',
        ]);
    }
}
