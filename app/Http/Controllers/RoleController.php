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
            description: 'Tiningnan ang listahan ng roles ('.$roles->total().' records)',
        );

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        SysLogService::record(
            action: 'read',
            table: 'roles',
            description: 'Binuksan ang form para gumawa ng bagong role',
        );

        return view('roles.create');
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create($request->validated());

        SysLogService::record(
            action: 'create',
            table: 'roles',
            recordId: $role->id,
            newValues: $role->only(['name', 'slug', 'description']),
            description: 'Gumawa ng bagong role: '.$role->name,
        );

        return redirect()
            ->route('roles.index')
            ->with('success', 'Matagumpay na nalikha ang role.');
    }

    public function show(Role $role): View
    {
        $this->authorize('view', $role);

        $role->loadCount('users');

        SysLogService::record(
            action: 'read',
            table: 'roles',
            recordId: $role->id,
            description: 'Tiningnan ang role: '.$role->name,
        );

        return view('roles.show', compact('role'));
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        SysLogService::record(
            action: 'read',
            table: 'roles',
            recordId: $role->id,
            description: 'Binuksan ang edit form para sa role: '.$role->name,
        );

        return view('roles.edit', compact('role'));
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
            description: 'In-update ang role: '.$role->name,
        );

        return redirect()
            ->route('roles.index')
            ->with('success', 'Matagumpay na na-update ang role.');
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
            description: 'Binura ang role: '.$name,
        );

        return redirect()
            ->route('roles.index')
            ->with('success', 'Matagumpay na nabura ang role.');
    }
}
