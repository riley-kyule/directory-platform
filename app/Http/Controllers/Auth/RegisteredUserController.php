<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Enums\OnboardingStatus;
use App\Enums\ProviderType;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'provider_type' => [
                Rule::requiredIf($request->string('account_type')->toString() === AccountType::Provider->value),
                'nullable',
                Rule::enum(ProviderType::class),
                Rule::prohibitedIf($request->string('account_type')->toString() === AccountType::Member->value),
            ],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'account_type' => $request->enum('account_type', AccountType::class),
            'provider_type' => $request->enum('provider_type', ProviderType::class),
            'onboarding_status' => $request->string('account_type')->toString() === AccountType::Provider->value
                ? OnboardingStatus::InProgress
                : OnboardingStatus::Registered,
            'onboarding_started_at' => $request->string('account_type')->toString() === AccountType::Provider->value
                ? now()
                : null,
            'last_onboarding_activity_at' => now(),
            'last_seen_at' => now(),
        ]);

        $subscriberRole = Role::query()->firstOrCreate(
            ['slug' => 'subscriber'],
            ['name' => 'Subscriber', 'is_system' => true],
        );
        $user->roles()->attach($subscriberRole);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
