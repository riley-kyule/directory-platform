<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Profile;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function create(Profile $profile): View
    {
        abort_unless(Profile::query()->publiclyVisible()->whereKey($profile->id)->exists(), 404);

        return view('directory.review-form', [
            'profile' => $profile,
            'metaTitle' => 'Review '.$profile->display_name.' — '.config('app.name'),
            'metaDescription' => 'Leave a review for a directory profile.',
            'canonicalUrl' => route('directory.profiles.reviews.create', $profile),
            'robots' => 'noindex,nofollow',
        ]);
    }

    public function store(StoreReviewRequest $request, Profile $profile): RedirectResponse
    {
        $validated = $request->validated();
        $email = strtolower($validated['email']);
        $fingerprintInput = ($request->ip() ?? 'unknown').'|'.str($request->userAgent())->limit(250);

        Review::query()->create([
            'profile_id' => $profile->id,
            'reviewer_name' => $validated['reviewer_name'] ?? null,
            'reviewer_email' => $email,
            'reviewer_email_hash' => hash('sha256', $email),
            'rating' => $validated['rating'],
            'body' => $validated['body'],
            'status' => 'pending',
            'source_fingerprint' => hash_hmac('sha256', $fingerprintInput, config('app.key')),
        ]);

        return redirect()->route('directory.profiles.show', $profile->slug)
            ->with('review_status', 'Thanks — your review is awaiting approval.');
    }
}
