<div>
    <h4 class="mb-3 text-sm font-semibold text-gray-900">Approval Routes</h4>
    <hr class="mb-4 border-gray-200">

    <div class="overflow-hidden rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Step</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Approver</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">Batch Approve</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-600">View Attendance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($steps as $step)
                    @forelse ($step->members as $member)
                        <tr>
                            <td class="px-3 py-2 text-gray-800">{{ $step->step_no }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $member->user?->name ?? 'User #'.$member->user_id }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $member->allow_batch_approve ? 'Yes' : 'No' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $member->allow_view_attendance ? 'Yes' : 'No' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-2 text-gray-800">{{ $step->step_no }}</td>
                            <td colspan="3" class="px-3 py-2 text-gray-500">No approvers assigned for this step.</td>
                        </tr>
                    @endforelse
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-8 text-center text-gray-500">No approval routes configured.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-gray-500">
        Route editing (add/remove steps and approvers) will be added in a follow-up update. Form type selection is active for viewing configured routes.
    </p>
</div>
