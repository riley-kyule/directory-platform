<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrivilegedMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! config('security.privileged_mfa_enforced') || ! $user || ! $user->isPrivileged()) {
            return $next($request);
        }

        if ($request->routeIs('mfa.*') || $request->routeIs('logout')) {
            return $next($request);
        }

        if (! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
            return redirect()->guest(route('mfa.setup'));
        }

        $passedAt = $request->session()->get('mfa_passed_at');
        $validAfter = now()->subHours(config('security.privileged_mfa_session_hours'))->timestamp;
        if (! is_int($passedAt) || $passedAt < $validAfter) {
            return redirect()->guest(route('mfa.challenge'));
        }

        return $next($request);
    }
}
