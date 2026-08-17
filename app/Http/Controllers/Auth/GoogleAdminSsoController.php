<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Throwable;

class GoogleAdminSsoController extends Controller
{
    private const STATE_TTL_SECONDS = 600;

    public function redirect(): RedirectResponse
    {
        abort_unless($this->configured(), 404);

        return Socialite::driver('google')
            ->stateless()
            ->with(['state' => $this->makeState()])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->configured(), 404);

        if (! $this->validState($request->query('state'))) {
            $this->audit($request, null, 'security.google-sso-failed', 'invalid-or-expired-oauth-state');

            return $this->rejected();
        }

        try {
            /** @var GoogleUser $googleUser */
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Throwable $exception) {
            report($exception);
            $this->audit($request, null, 'security.google-sso-failed', 'oauth-callback-failed');

            return $this->rejected();
        }

        $raw = $googleUser->getRaw();
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $subject = trim((string) $googleUser->getId());
        $verified = filter_var($raw['verified_email'] ?? $raw['email_verified'] ?? false, FILTER_VALIDATE_BOOL);

        if (! $verified || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $subject === '') {
            $this->audit($request, null, 'security.google-sso-rejected', 'invalid-google-identity');

            return $this->rejected();
        }

        if (! $this->domainAllowed($email)) {
            $this->audit($request, null, 'security.google-sso-rejected', 'email-domain-not-allowed');

            return $this->rejected();
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user || ! $user->isPrivileged()) {
            $this->audit($request, $user, 'security.google-sso-rejected', 'existing-staff-account-required');

            return $this->rejected();
        }

        if ($user->google_subject !== null && ! hash_equals($user->google_subject, $subject)) {
            $this->audit($request, $user, 'security.google-sso-rejected', 'google-identity-mismatch');

            return $this->rejected();
        }

        if (User::withTrashed()->where('google_subject', $subject)->where('id', '!=', $user->id)->exists()) {
            $this->audit($request, $user, 'security.google-sso-rejected', 'google-identity-already-linked');

            return $this->rejected();
        }

        try {
            DB::transaction(function () use ($user, $subject): void {
                $user->forceFill([
                    'google_subject' => $subject,
                    'google_sso_linked_at' => $user->google_sso_linked_at ?? now(),
                    'google_sso_last_login_at' => now(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'last_seen_at' => now(),
                ])->save();
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->audit($request, $user, 'security.google-sso-failed', 'google-identity-link-failed');

            return $this->rejected();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->audit($request, $user, 'security.google-sso-login', 'verified-existing-staff');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function makeState(): string
    {
        return Crypt::encryptString(json_encode([
            'provider' => 'google',
            'nonce' => Str::random(40),
            'expires_at' => now()->addSeconds(self::STATE_TTL_SECONDS)->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    private function validState(mixed $state): bool
    {
        if (! is_string($state) || $state === '') {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($state), true, flags: JSON_THROW_ON_ERROR);

            return is_array($payload)
                && ($payload['provider'] ?? null) === 'google'
                && is_string($payload['nonce'] ?? null)
                && strlen($payload['nonce']) === 40
                && is_int($payload['expires_at'] ?? null)
                && $payload['expires_at'] >= now()->timestamp;
        } catch (Throwable) {
            return false;
        }
    }

    private function domainAllowed(string $email): bool
    {
        $allowed = config('services.google.admin_allowed_domains', []);

        return $allowed === [] || in_array(Str::afterLast($email, '@'), $allowed, true);
    }

    private function rejected(): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'google_sso' => 'Google sign-in could not be completed for an authorized staff account.',
        ]);
    }

    private function audit(Request $request, ?User $user, string $action, string $reason): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $user?->id,
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $user?->id,
            'new_state' => ['provider' => 'google'],
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent())->limit(500)->toString(),
        ]);
    }
}
