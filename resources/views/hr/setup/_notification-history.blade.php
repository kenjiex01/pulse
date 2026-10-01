<div class="space-y-4">
    <p class="text-sm text-gray-600">
        Probationary end emails sent to HR. Each row is one email (one employee and day offset).
    </p>

    @if ($notificationHistory->isEmpty())
        <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
            No probationary notification emails have been sent yet.
        </p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-700">Sent</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-700">Employee</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-700">Days before</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-700">Probationary end</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($notificationHistory as $log)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-900">
                                {{ $log->sent_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?: '—' }}
                            </td>
                            <td class="px-3 py-2 text-gray-900">
                                @if ($log->employee)
                                    <div class="font-medium">{{ $log->employee->full_name }}</div>
                                    <div class="text-xs text-gray-500">{{ $log->employee->employee_number }}</div>
                                @else
                                    <span class="text-gray-500">Employee #{{ $log->employee_id }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $log->days_before }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">
                                {{ $log->probationary_end_date?->format('M j, Y') ?: '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($notificationHistory->hasPages())
            <div class="flex justify-end">
                {{ $notificationHistory->appends(['tab' => 'history'])->links() }}
            </div>
        @endif
    @endif
</div>
