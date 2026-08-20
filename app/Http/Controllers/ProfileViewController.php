<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\KnownCrawler;
use App\Services\ProfileViewTracker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfileViewController extends Controller
{
    public function __invoke(Request $request, ProfileViewTracker $tracker, KnownCrawler $crawler): Response
    {
        $validated = $request->validate([
            'profile' => ['required', 'uuid'],
        ]);

        if ($crawler->matches($request->userAgent())) {
            return response()->noContent();
        }

        $profile = Profile::query()
            ->publiclyVisible()
            ->where('public_id', $validated['profile'])
            ->firstOrFail();

        $tracker->record($profile);

        return response()->noContent();
    }
}
