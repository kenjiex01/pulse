<div class="memo-preview-html-shell" data-memo-preview-html>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-5 py-4 sm:px-6">
            <h3 class="text-center text-lg font-semibold text-gray-900">{{ $form->name }}</h3>
            <p class="mt-2 text-center text-xs text-gray-500">
                {{ $employee->full_name }} · {{ $violation_label }}
                @if ($control_no !== '')
                    · {{ $control_no }}
                @endif
            </p>
            @if ($sent_at)
                <p class="mt-1 text-center text-xs text-gray-400">Sent {{ $sent_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
            @endif
        </div>
        <div class="bg-gray-100 p-3 sm:p-4">
            <iframe
                src="{{ route(\App\Support\TimekeepingMemo::routeName('sent-preview-html'), $submission) }}"
                title="Sent memo preview"
                class="block w-full rounded-lg border border-gray-200 bg-gray-200"
                style="height: min(85vh, 1600px); min-height: 900px;"
            ></iframe>
        </div>
    </div>
</div>
