<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function __construct(private readonly TotpService $totp) {}

    public function setup(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()->isPrivileged(), 403);
        if ($request->user()->two_factor_confirmed_at) {
            return redirect()->route('mfa.challenge');
        }

        $secret = $request->session()->get('mfa_setup_secret') ?? $this->totp->generateSecret();
        $request->session()->put('mfa_setup_secret', $secret);

        return view('auth.mfa-setup', [
            'secret' => $secret,
            'provisioningUri' => $this->totp->provisioningUri($request->user(), $secret),
        ]);
    }

    public function confirm(Request $request): View
    {
        abort_unless($request->user()->isPrivileged(), 403);
        abort_if($request->user()->two_factor_confirmed_at, 409, 'MFA is already configured.');
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $secret = $request->session()->get('mfa_setup_secret');
        if (! is_string($secret) || ! $this->totp->verify($secret, $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is invalid or expired.']);
        }

        $recoveryCodes = $this->totp->recoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => collect($recoveryCodes)
                ->map(fn (string $code) => $this->recoveryHash($code))
                ->all(),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget('mfa_setup_secret');
        $request->session()->put('mfa_passed_at', now()->timestamp);
        $request->session()->regenerate();
        $this->audit($request, 'security.mfa-enabled');

        return view('auth.mfa-recovery-codes', ['recoveryCodes' => $recoveryCodes]);
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()->isPrivileged(), 403);
        if (! $request->user()->two_factor_confirmed_at) {
            return redirect()->route('mfa.setup');
        }

        return view('auth.mfa-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isPrivileged(), 403);
        if (! $request->user()->two_factor_confirmed_at || ! $request->user()->two_factor_secret) {
            return redirect()->route('mfa.setup');
        }
        $validated = $request->validate(['credential' => ['required', 'string', 'max:32']]);
        $credential = strtoupper(trim($validated['credential']));
        $validTotp = preg_match('/^\d{6}$/', $credential)
            && $this->totp->verify($request->user()->two_factor_secret, $credential);
        $usedRecovery = false;

        if (! $validTotp) {
            $usedRecovery = $this->consumeRecoveryCode($request->user(), $credential);
        }
        if (! $validTotp && ! $usedRecovery) {
            throw ValidationException::withMessages(['credential' => 'The authenticator or recovery code is invalid.']);
        }

        $request->session()->regenerate();
        $request->session()->put('mfa_passed_at', now()->timestamp);
        $this->audit($request, $usedRecovery ? 'security.mfa-recovery-used' : 'security.mfa-challenged');

        return redirect()->intended(route('dashboard'));
    }

    private function consumeRecoveryCode(User $user, string $credential): bool
    {
        return DB::transaction(function () use ($user, $credential): bool {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $codes = $user->two_factor_recovery_codes ?? [];
            $position = array_search($this->recoveryHash($credential), $codes, true);
            if ($position === false) {
                return false;
            }
            unset($codes[$position]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

            return true;
        });
    }

    private function recoveryHash(string $code): string
    {
        return hash_hmac('sha256', strtoupper(trim($code)), config('app.key'));
    }

    private function audit(Request $request, string $action): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $request->user()->id,
            'new_state' => ['mfa_confirmed' => true],
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent())->limit(500)->toString(),
        ]);
    }
}
