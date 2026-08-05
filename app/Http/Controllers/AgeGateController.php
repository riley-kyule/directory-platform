<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAgeConfirmed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AgeGateController extends Controller
{
    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate(['redirect' => ['nullable', 'string']]);

        return redirect($this->safeRedirect($validated['redirect'] ?? null))
            ->withCookie(cookie(EnsureAgeConfirmed::COOKIE, '1', 60 * 24 * 365, sameSite: 'lax'));
    }

    /**
     * Only ever redirects back within this site — an unvalidated "redirect"
     * value here would be a textbook open-redirect vector.
     */
    private function safeRedirect(?string $target): string
    {
        if (! $target) {
            return route('directory.home');
        }

        $host = parse_url($target, PHP_URL_HOST);
        if ($host !== null && $host !== request()->getHost()) {
            return route('directory.home');
        }

        return $target;
    }
}
