<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffUserRequest;
use App\Http\Requests\UpdateUserRolesRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /** Roles this panel grants/revokes. "subscriber" is assigned automatically at
     * registration and isn't a privilege to hand out here. */
    private const MANAGEABLE_ROLES = ['admin', 'csr', 'seo'];

    public function __construct(private readonly AccountDeletionService $accountDeletion) {}

    public function index(Request $request): View
    {
        Gate::authorize('roles.manage');

        $search = trim((string) $request->query('q', ''));
        $users = User::query()
            ->with('roles')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'manageableRoles' => Role::query()->whereIn('slug', self::MANAGEABLE_ROLES)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('roles.manage');

        return view('admin.users.create', [
            'manageableRoles' => Role::query()->whereIn('slug', self::MANAGEABLE_ROLES)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreStaffUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($request, $validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'status' => 'active',
                'account_type' => AccountType::Member,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $roleIds = Role::query()->whereIn('slug', $validated['roles'])->pluck('id');
            $user->roles()->attach($roleIds);

            $this->audit($request, 'users.create-staff', $user, [], [
                'email' => $user->email,
                'roles' => $validated['roles'],
            ]);

            return $user;
        });

        return redirect()->route('admin.users.index')->with('status', "{$user->name} created with roles: ".implode(', ', $validated['roles']).'.');
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user): RedirectResponse
    {
        $requested = $request->validated('roles', []);
        $previousRoles = $user->roles()->whereIn('slug', self::MANAGEABLE_ROLES)->pluck('slug')->all();

        if (in_array('admin', $previousRoles, true) && ! in_array('admin', $requested, true)
            && Role::query()->where('slug', 'admin')->firstOrFail()->users()->count() === 1) {
            return back()->withErrors(['roles' => 'At least one Admin must remain — assign another Admin before removing this one.']);
        }

        DB::transaction(function () use ($request, $user, $requested, $previousRoles): void {
            $manageableRoleIds = Role::query()->whereIn('slug', self::MANAGEABLE_ROLES)->pluck('id', 'slug');
            $user->roles()->detach($manageableRoleIds->only(self::MANAGEABLE_ROLES)->values());
            $user->roles()->attach($manageableRoleIds->only($requested)->values());

            $this->audit($request, 'users.update-roles', $user, ['roles' => $previousRoles], ['roles' => $requested]);
        });

        return back()->with('status', "Roles updated for {$user->name}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('roles.manage');

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account from this panel.']);
        }

        if ($user->hasRole('admin') && Role::query()->where('slug', 'admin')->firstOrFail()->users()->count() === 1) {
            return back()->withErrors(['user' => 'At least one Admin must remain — assign another Admin before deleting this one.']);
        }

        $name = $user->name;
        $this->accountDeletion->deleteAccount(
            $user,
            $request->user(),
            'users.delete',
            'Account deleted by an administrator.',
        );

        return back()->with('status', "{$name} deleted.");
    }

    /** @param array<string, mixed> $previous
     * @param  array<string, mixed>  $new
     */
    private function audit(Request $request, string $action, User $target, array $previous, array $new): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $target->id,
            'previous_state' => $previous,
            'new_state' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent())->limit(500)->toString(),
        ]);
    }
}
