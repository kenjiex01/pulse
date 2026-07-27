<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\SidebarNavigationService;
use App\Services\SysLogService;
use App\Support\LiveTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->with('roles')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(LiveTable::perPage($request, 10))
            ->withQueryString();

        $roles = Role::query()->orderBy('name')->get();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: 'users',
                description: 'Viewed users list ('.$users->total().' records)',
            );
        }

        $viewData = [
            'users' => $users,
            'roles' => $roles,
            'search' => $search,
            'openViewUserId' => $request->input('view_user'),
            'openEditUserId' => $request->input('edit_user'),
        ];

        if ($request->ajax()) {
            return view('users._results', $viewData);
        }

        return view('users.index', $viewData);
    }

    public function create(): RedirectResponse
    {
        $this->authorize('create', User::class);

        SysLogService::record(
            action: 'read',
            table: 'users',
            description: 'Opened create user form',
        );

        return redirect()->route('users.index', ['create' => 1]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('role_ids');
        $user = User::query()->create($data);
        $user->roles()->sync($request->input('role_ids', []));
        SidebarNavigationService::forgetForUser($user->id);

        SysLogService::record(
            action: 'create',
            table: 'users',
            recordId: $user->id,
            newValues: $user->fresh()->logSnapshot(),
            description: 'Created user: '.$user->name,
        );

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user): RedirectResponse
    {
        $this->authorize('view', $user);

        SysLogService::record(
            action: 'read',
            table: 'users',
            recordId: $user->id,
            description: 'Viewed user: '.$user->name,
        );

        return redirect()->route('users.index', ['view_user' => $user->id]);
    }

    public function edit(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        SysLogService::record(
            action: 'read',
            table: 'users',
            recordId: $user->id,
            description: 'Opened edit form for '.$user->name,
        );

        return redirect()->route('users.index', ['edit_user' => $user->id]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $adminRoleId = Role::query()->where('slug', Role::SLUG_ADMIN)->value('id');
        $newRoleIds = array_map('intval', $request->input('role_ids', []));

        if (
            $user->isAdmin()
            && User::query()->whereHas('roles', fn ($query) => $query->where('slug', Role::SLUG_ADMIN))->count() <= 1
            && ! in_array((int) $adminRoleId, $newRoleIds, true)
        ) {
            return back()
                ->withInput()
                ->withErrors(['role_ids' => 'Cannot remove the administrator role from the last administrator.']);
        }

        $oldValues = $user->logSnapshot();
        $data = $request->safe()->except('role_ids');

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);
        $user->roles()->sync($newRoleIds);
        SidebarNavigationService::forgetForUser($user->id);

        SysLogService::record(
            action: 'update',
            table: 'users',
            recordId: $user->id,
            oldValues: $oldValues,
            newValues: $user->fresh()->logSnapshot(),
            description: 'Updated user: '.$user->name,
        );

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $oldValues = $user->logSnapshot();
        $name = $user->name;
        $userId = $user->id;

        $user->delete();

        SysLogService::record(
            action: 'delete',
            table: 'users',
            recordId: $userId,
            oldValues: $oldValues,
            description: 'Deleted user: '.$name,
        );

        return redirect()
            ->route('users.index')
            ->with('success', 'User deleted successfully.');
    }
}
