<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()->with('role')->orderBy('name')->paginate(10);

        SysLogService::record(
            action: 'read',
            table: 'users',
            description: 'Viewed users list ('.$users->total().' records)',
        );

        return view('users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
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
        $user = User::query()->create($request->validated());

        SysLogService::record(
            action: 'create',
            table: 'users',
            recordId: $user->id,
            newValues: $user->logSnapshot(),
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

        if (
            $user->isAdmin()
            && User::query()->whereHas('role', fn ($q) => $q->where('slug', Role::SLUG_ADMIN))->count() <= 1
            && (int) $request->input('role_id') !== (int) $adminRoleId
        ) {
            return back()
                ->withInput()
                ->withErrors(['role_id' => 'Cannot remove the role from the last administrator.']);
        }

        $oldValues = $user->logSnapshot();
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

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
