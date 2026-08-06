<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageReviewRequest;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\Review;
use App\Services\PublicPageCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewModerationController extends Controller
{
    public function __construct(private readonly PublicPageCache $pageCache) {}

    public function index(Request $request): View
    {
        Gate::authorize('reviews.view');
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'published', 'rejected'])],
        ]);
        $status = $filters['status'] ?? 'pending';

        return view('staff.reviews.index', [
            'reviews' => Review::query()
                ->with(['profile:id,display_name,slug', 'moderatedBy:id,name'])
                ->where('status', $status)
                ->oldest()
                ->paginate(30)
                ->withQueryString(),
            'status' => $status,
        ]);
    }

    public function update(ManageReviewRequest $request, Review $review): RedirectResponse
    {
        DB::transaction(function () use ($request, $review): void {
            $review = Review::query()->lockForUpdate()->findOrFail($review->id);
            $action = $request->validated('action');
            $previous = $review->only(['status', 'moderation_reason']);

            $review->update([
                'status' => $action === 'approve' ? 'published' : 'rejected',
                'moderation_reason' => $action === 'reject' ? $request->validated('reason') : null,
                'moderated_by' => $request->user()->id,
                'moderated_at' => now(),
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'reviews.'.$action,
                'target_type' => 'review',
                'target_id' => $review->id,
                'previous_state' => $previous,
                'new_state' => $review->fresh()->only(['status', 'moderation_reason']),
                'reason' => $request->validated('reason'),
                'ip_address' => $request->ip(),
                'user_agent' => str($request->userAgent())->limit(500)->toString(),
            ]);
        });

        $profile = Profile::query()->find($review->profile_id);
        if ($profile) {
            $this->pageCache->forgetForProfile($profile);
        }

        return back()->with('status', 'Review updated.');
    }
}
