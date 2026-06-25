<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Services\SysLogService;
use App\Support\LiveTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Role::class);

        $search = $request->string('search')->trim()->toString();

        $roles = Role::query()
            ->withCount('users')
            ->with(['modules', 'subModules', 'users'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(LiveTable::perPage($request, 10))
            ->withQueryString();

        $modules = Module::query()
            ->with(['subModules' => fn ($query) => $query->where('is_active', true)])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $allUsers = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_ids' => $user->roles->pluck('id')->values()->all(),
                'role_names' => $user->roles->pluck('name')->values()->all(),
            ]);

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: 'roles',
                description: 'Viewed roles list ('.$roles->total().' records)',
            );
        }

        $viewData = [
            'roles' => $roles,
            'modules' => $modules,
            'allUsers' => $allUsers,
            'search' => $search,
            'openViewRoleId' => $request->input('view_role'),
            'openEditRoleId' => $request->input('edit_role'),
        ];

        if ($request->ajax()) {
            return view('roles._results', $viewData);
        }

        return view('roles.index', $viewData);
    }

    public function create(): RedirectResponse
    {
        $this->authorize('create', Role::class);

        SysLogService::record(
            action: 'read',
            table: 'roles',
            description: 'Opened create role form',
        );

        return redirect()->route('roles.index', ['create' => 1]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create($request->safe()->only(['name', 'slug', 'description']));
        $this->syncRolePermissions($role, $request->input('modules', []), $request->input('sub_modules', []));
        $role->syncMembers($request->input('member_ids', []));

        SysLogService::record(
            action: 'create',
            table: 'roles',
            recordId: $role->id,
            newValues: $role->only(['name', 'slug', 'description']),
            description: 'Created role: '.$role->name,
        );

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function show(Role $role): RedirectResponse
    {
        $this->authorize('view', $role);

        SysLogService::record(
            action: 'read',
            table: 'roles',
            recordId: $role->id,
            description: 'Viewed role: '.$role->name,
        );

        return redirect()->route('roles.index', ['view_role' => $role->id]);
    }

    public function edit(Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        SysLogService::record(
            action: 'read',
            table: 'roles',
            recordId: $role->id,
            description: 'Opened edit form for role: '.$role->name,
        );

        return redirect()->route('roles.index', ['edit_role' => $role->id]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        if ($role->isAdmin()) {
            $adminUsers = User::query()
                ->whereHas('roles', fn ($query) => $query->where('slug', Role::SLUG_ADMIN))
                ->pluck('id')
                ->all();

            $memberIds = array_map('intval', $request->input('member_ids', []));

            if ($adminUsers !== [] && array_diff($adminUsers, $memberIds) !== []) {
                return back()
                    ->withInput()
                    ->withErrors(['member_ids' => 'The administrator role must include all administrator users.']);
            }
        }

        $oldValues = $role->only(['name', 'slug', 'description']);
        $role->update($request->safe()->only(['name', 'description']));
        $this->syncRolePermissions($role, $request->input('modules', []), $request->input('sub_modules', []));
        $role->syncMembers($request->input('member_ids', []));

        SysLogService::record(
            action: 'update',
            table: 'roles',
            recordId: $role->id,
            oldValues: $oldValues,
            newValues: $role->only(['name', 'slug', 'description']),
            description: 'Updated role: '.$role->name,
        );

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $oldValues = $role->only(['name', 'slug', 'description']);
        $name = $role->name;
        $roleId = $role->id;

        $role->delete();

        SysLogService::record(
            action: 'delete',
            table: 'roles',
            recordId: $roleId,
            oldValues: $oldValues,
            description: 'Deleted role: '.$name,
        );

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    private function syncRolePermissions(Role $role, array $modulesInput, array $subModulesInput): void
    {
        $parentModuleIds = Module::query()
            ->whereHas('subModules', fn ($query) => $query->where('is_active', true))
            ->pluck('id')
            ->all();

        $modulePermissions = collect($modulesInput)
            ->except($parentModuleIds)
            ->all();

        $role->syncModulePermissions($modulePermissions);
        $role->syncSubModulePermissions($subModulesInput);
    }
}
