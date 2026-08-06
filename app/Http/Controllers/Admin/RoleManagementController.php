<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleManagementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('roles.manage');

        return view('admin.roles.index', [
            'roles' => Role::query()->with('permissions')->withCount('users')->orderBy('name')->get(),
            'permissionGroups' => Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $slug = Str::slug($validated['name']);

        if (! $slug) {
            return back()->withInput()->withErrors(['name' => 'Enter a name that can produce a stable identifier.']);
        }
        if (Role::query()->where('slug', $slug)->exists()) {
            return back()->withInput()->withErrors(['name' => 'A role with this name already exists.']);
        }

        $role = DB::transaction(function () use ($request, $validated, $slug): Role {
            $role = Role::query()->create([
                'name' => $validated['name'],
                'slug' => $slug,
                'is_system' => false,
            ]);
            $permissionIds = $validated['permissions'] ?? [];
            $role->permissions()->sync($permissionIds);

            $this->audit($request, 'roles.create', $role, [], [
                'name' => $role->name,
                'permissions' => Permission::query()->whereKey($permissionIds)->pluck('slug')->all(),
            ]);

            return $role;
        });

        return redirect()->route('admin.roles.index')->with('status', "Role {$role->name} created.");
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();
        $permissionIds = $validated['permissions'] ?? [];

        DB::transaction(function () use ($request, $role, $validated, $permissionIds): void {
            $previous = [
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('slug')->all(),
            ];

            if (array_key_exists('name', $validated)) {
                $role->update(['name' => $validated['name']]);
            }
            $role->permissions()->sync($permissionIds);

            $this->audit($request, 'roles.update', $role, $previous, [
                'name' => $role->fresh()->name,
                'permissions' => Permission::query()->whereKey($permissionIds)->pluck('slug')->all(),
            ]);
        });

        return back()->with('status', "Role {$role->name} updated.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        Gate::authorize('roles.manage');

        if ($role->is_system) {
            return back()->withErrors(['role' => 'System roles cannot be deleted.']);
        }
        if ($role->users()->count() > 0) {
            return back()->withErrors(['role' => 'Reassign or remove users from this role before deleting it.']);
        }

        $this->audit($request, 'roles.delete', $role, [
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('slug')->all(),
        ], []);
        $role->delete();

        return back()->with('status', "Role {$role->name} deleted.");
    }

    /** @param array<string, mixed> $previous
     * @param  array<string, mixed>  $new
     */
    private function audit(Request $request, string $action, Role $target, array $previous, array $new): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'target_type' => 'role',
            'target_id' => $target->id,
            'previous_state' => $previous,
            'new_state' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent())->limit(500)->toString(),
        ]);
    }
}
