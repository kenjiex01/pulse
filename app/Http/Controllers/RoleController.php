<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Role;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()->withCount('users')->orderBy('name')->paginate(10);

        SysLogService::record(
            action: 'read',
            table: 'roles',
            description: 'Viewed roles list ('.$roles->total().' records)',
        );

        return view('roles.index', compact('roles'));
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
        $role = Role::query()->create($request->validated());

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

        $oldValues = $role->only(['name', 'slug', 'description']);
        $role->update($request->validated());

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
}
