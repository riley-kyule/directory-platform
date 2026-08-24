<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMailSettingsRequest;
use App\Models\AuditLog;
use App\Models\MailSetting;
use App\Services\MailConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class MailSettingsController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('settings.manage');

        return view('admin.settings.mail', ['mailSettings' => MailSetting::query()->firstOrFail()]);
    }

    public function update(UpdateMailSettingsRequest $request, MailConfiguration $configuration): RedirectResponse
    {
        $settings = MailSetting::query()->firstOrFail();
        $validated = $request->validated();
        $previous = $this->auditable($settings);

        if (blank($validated['smtp_password'] ?? null)) {
            unset($validated['smtp_password']);
        }
        if ($validated['mailer'] === 'sendmail') {
            $validated = collect($validated)->except(['smtp_scheme', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'])->all();
        }

        $settings->update($validated + ['updated_by' => $request->user()->id]);
        $configuration->apply($settings->fresh());

        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => 'settings.mail-update',
            'target_type' => 'mail-configuration',
            'target_id' => $settings->id,
            'previous_state' => $previous,
            'new_state' => $this->auditable($settings->fresh()),
            'reason' => 'Updated mail delivery configuration from Admin.',
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent())->limit(500)->toString(),
        ]);

        return back()->with('status', 'Mail delivery settings updated.');
    }

    public function test(Request $request, MailConfiguration $configuration): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $validated = $request->validate(['recipient' => ['required', 'email', 'max:255']]);
        $configuration->apply();

        try {
            Mail::raw('Mail delivery from '.config('app.name').' is working.', function ($message) use ($validated): void {
                $message->to($validated['recipient'])->subject(config('app.name').' mail test');
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['recipient' => 'The test message could not be sent. Check the delivery settings and server mail log.']);
        }

        return back()->with('status', 'Test email sent to '.$validated['recipient'].'.');
    }

    /** @return array<string, mixed> */
    private function auditable(MailSetting $settings): array
    {
        return $settings->only([
            'mailer', 'from_address', 'from_name', 'sendmail_path', 'smtp_scheme',
            'smtp_host', 'smtp_port', 'smtp_username',
        ]) + ['smtp_password_configured' => filled($settings->smtp_password)];
    }
}
