<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfileReportRequest;
use App\Models\Profile;
use App\Models\ProfileReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileReportController extends Controller
{
    public function create(Profile $profile): View
    {
        abort_unless(Profile::query()->publiclyVisible()->whereKey($profile->id)->exists(), 404);

        return view('directory.report', [
            'profile' => $profile,
            'categories' => ProfileReport::CATEGORIES,
            'metaTitle' => 'Report '.$profile->display_name.' — '.config('app.name'),
            'metaDescription' => 'Send a confidential concern about a directory profile.',
            'canonicalUrl' => route('directory.profiles.report.create', $profile),
            'robots' => 'noindex,nofollow',
        ]);
    }

    public function store(StoreProfileReportRequest $request, Profile $profile): RedirectResponse
    {
        $validated = $request->validated();
        $email = strtolower($validated['email']);
        $fingerprintInput = ($request->ip() ?? 'unknown').'|'.str($request->userAgent())->limit(250);
        $report = ProfileReport::query()->create([
            'profile_id' => $profile->id,
            'reporter_user_id' => $request->user()?->id,
            'reporter_email' => $email,
            'reporter_email_hash' => hash('sha256', $email),
            'category' => $validated['category'],
            'details' => $validated['details'],
            'priority' => in_array($validated['category'], ProfileReport::URGENT_CATEGORIES, true) ? 'urgent' : 'normal',
            'status' => 'new',
            'source_fingerprint' => hash_hmac('sha256', $fingerprintInput, config('app.key')),
        ]);

        return redirect()->route('directory.profiles.show', $profile->slug)
            ->with('report_status', 'Thank you. Your confidential report reference is '.$report->public_id.'.');
    }
}
