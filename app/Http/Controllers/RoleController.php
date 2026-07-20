<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\User;
use App\Services\RoleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService)
    {
        $this->authorizeResource(Role::class, 'role');
    }

    public function index(): View
    {
        $roles = Role::query()
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) {
                $role->user_count = User::role($role->name)->count();
                $role->is_system = in_array($role->name, RoleSeeder::ROLES, true);

                return $role;
            });

        return view('roles.index', ['roles' => $roles]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = $this->roleService->create($request->validated());

        return redirect()->route('roles.edit', $role)->with('status', "Role \"{$role->name}\" berhasil dibuat, silakan atur permission-nya.");
    }

    public function edit(Role $role): View
    {
        $permissionGroups = Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => Str::before($permission->name, '.'));

        return view('roles.edit', [
            'role' => $role,
            'permissionGroups' => $permissionGroups,
            'rolePermissionNames' => $role->permissions()->pluck('name')->all(),
            'isSuperadmin' => $role->name === 'superadmin',
            'isBuiltInRole' => in_array($role->name, RoleSeeder::ROLES, true),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        try {
            $this->roleService->updatePermissions($role, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('roles.edit', $role)->with('status', 'Permission role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        try {
            $this->roleService->delete($role);
        } catch (RuntimeException $exception) {
            return redirect()->route('roles.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('roles.index')->with('status', 'Role berhasil dihapus.');
    }

    public function resetToDefault(Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        try {
            $this->roleService->resetToSeederDefault($role);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('roles.edit', $role)->with('status', "Permission role \"{$role->name}\" dikembalikan ke default sistem.");
    }
}
