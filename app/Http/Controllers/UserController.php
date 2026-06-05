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
            description: 'Tiningnan ang listahan ng users ('.$users->total().' records)',
        );

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        SysLogService::record(
            action: 'read',
            table: 'users',
            description: 'Binuksan ang form para gumawa ng bagong user',
        );

        return view('users.create', [
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::query()->create($request->validated());

        SysLogService::record(
            action: 'create',
            table: 'users',
            recordId: $user->id,
            newValues: $user->logSnapshot(),
            description: 'Gumawa ng bagong user: '.$user->name,
        );

        return redirect()
            ->route('users.index')
            ->with('success', 'Matagumpay na nalikha ang user.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load('role');

        SysLogService::record(
            action: 'read',
            table: 'users',
            recordId: $user->id,
            description: 'Tiningnan ang user: '.$user->name,
        );

        return view('users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $user->load('role');

        SysLogService::record(
            action: 'read',
            table: 'users',
            recordId: $user->id,
            description: 'Binuksan ang edit form para kay '.$user->name,
        );

        return view('users.edit', [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
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
                ->withErrors(['role_id' => 'Hindi maaaring alisin ang role ng huling administrator.']);
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
            description: 'In-update ang user: '.$user->name,
        );

        return redirect()
            ->route('users.index')
            ->with('success', 'Matagumpay na na-update ang user.');
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
            description: 'Binura ang user: '.$name,
        );

        return redirect()
            ->route('users.index')
            ->with('success', 'Matagumpay na nabura ang user.');
    }
}
