@php
    $stepNumber = is_numeric($stepIndex) ? ((int) $stepIndex + 1) : '__NUMBER__';
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-approval-step>
    <div class="mb-3 flex items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-gray-900">Step <span data-step-number>{{ $stepNumber }}</span></h3>
        <button type="button" class="text-xs text-red-600 hover:text-red-800" data-remove-approval-step>Remove</button>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
        <div>
            <label class="form-label">Step name</label>
            <input type="text" data-field="name" class="form-input w-full" value="Approver" maxlength="150">
        </div>
        <div>
            <label class="form-label">Mode</label>
            <select data-field="mode" class="form-input w-full">
                @foreach ($approvalModes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Instructions</label>
            <textarea data-field="instructions" rows="2" class="form-input w-full"></textarea>
        </div>
        <div class="flex items-center gap-4 md:col-span-2">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" data-field="optional" class="rounded border-gray-300">
                Optional step
            </label>
        </div>
    </div>

    <div class="mt-4 border-t border-gray-100 pt-4">
        <div class="mb-2 flex items-center justify-between">
            <p class="text-sm font-medium text-gray-900">Assignees</p>
            <button type="button" class="btn-secondary !px-2 !py-1 text-xs" data-add-assignee>Add assignee</button>
        </div>
        <div class="space-y-2" data-assignee-rows>
            <div class="grid gap-2 md:grid-cols-[140px_1fr_auto]" data-assignee-row>
                <select data-field="assignee_type" class="form-input">
                    <option value="user">User</option>
                    <option value="role">Role</option>
                </select>
                <select data-field="user_id" class="form-input">
                    <option value="">Select user</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <select data-field="role_id" class="form-input hidden">
                    <option value="">Select role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                <button type="button" class="text-xs text-red-600" data-remove-assignee>Remove</button>
            </div>
        </div>
    </div>
</div>
