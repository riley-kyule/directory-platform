<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\ProfileConversionTracker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProfileConversionController extends Controller
{
    public function __invoke(Request $request, ProfileConversionTracker $tracker): Response
    {
        $validated = $request->validate([
            'profile' => ['required', 'uuid'],
            'channel' => ['required', Rule::in(ProfileConversionTracker::CHANNELS)],
            'placement' => ['required', Rule::in(ProfileConversionTracker::PLACEMENTS)],
        ]);

        $profile = Profile::query()
            ->publiclyVisible()
            ->where('public_id', $validated['profile'])
            ->firstOrFail();

        $tracker->record($profile, $validated['channel'], $validated['placement']);

        return response()->noContent();
    }
}
