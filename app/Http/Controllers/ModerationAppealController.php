<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModerationAppealRequest;
use App\Models\ModerationAppeal;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ModerationAppealController extends Controller
{
    public function store(StoreModerationAppealRequest $request, Profile $profile): RedirectResponse
    {
        $appeal = DB::transaction(function () use ($request, $profile): ModerationAppeal {
            $profile = Profile::query()->lockForUpdate()->findOrFail($profile->id);
            abort_unless($profile->hasActiveModerationRestriction(), 409, 'This profile has no active moderation restriction to appeal.');
            abort_if($profile->moderationAppeals()->where('status', 'pending')->exists(), 409, 'An appeal is already pending.');
            $action = $profile->moderationActions()
                ->whereIn('action', ['make_private', 'ban'])
                ->latest('created_at')
                ->firstOrFail();

            return ModerationAppeal::query()->create([
                'profile_id' => $profile->id,
                'moderation_action_id' => $action->id,
                'appellant_user_id' => $request->user()->id,
                'reason' => $request->validated('reason'),
                'status' => 'pending',
            ]);
        });

        return back()->with('status', 'Appeal submitted. Reference: '.$appeal->public_id);
    }
}
