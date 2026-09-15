@php
    /** @var array{form: \App\Models\CompanyDocumentForm, elements: list<array<string, mixed>>, preview_values: array<string, string>, selected_dates: list<string>} $preview */
    $form = $preview['form'];
    $typeLabel = \App\Models\TimekeepingMemoSetup::labelForType($memoFilters['violation_type'] ?? 'late');
    $dateSummary = collect($preview['selected_dates'])
        ->map(fn (string $date) => \Illuminate\Support\Carbon::parse($date)->format('M j, Y'))
        ->join(', ');

    $pdfQuery = [
        'date_from' => $memoFilters['date_from'],
        'date_to' => $memoFilters['date_to'],
        'violation_type' => $memoFilters['violation_type'],
        'min_count' => $memoFilters['min_count'] ?? null,
        'work_dates' => $preview['selected_dates'],
    ];
    $previewUrl = route(\App\Support\TimekeepingMemo::routeName('preview-html'), [$employee->employee_id] + $pdfQuery);
@endphp

<div class="memo-preview-html-shell" data-memo-preview-html>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-5 py-4 sm:px-6">
            <h3 class="text-center text-lg font-semibold text-gray-900">{{ $form->name }}</h3>
            @if ($form->description)
                <p class="mt-1 whitespace-pre-wrap text-center text-sm text-gray-600">{{ $form->description }}</p>
            @endif
            <p class="mt-2 text-center text-xs text-gray-500">
                Preview for {{ $employee->full_name }} · {{ $campusLabel }} · {{ $typeLabel }}
            </p>
            <p class="mt-1 text-center text-xs text-gray-400">
                {{ count($preview['selected_dates']) }} date(s): {{ $dateSummary }}
            </p>
            <p class="mt-3 text-center text-xs font-medium text-[#0B318F]">
                Legal size (8.5 × 14 in) — same layout as the memo PDF
            </p>
            @if ($form->requires_nte)
                @php($nteTemplate = \App\Models\CompanyDocumentForm::activeNteTemplate())
                <p class="mt-2 text-center text-xs font-medium text-amber-800">
                    Requires NTE — sending will also email
                    @if ($nteTemplate)
                        <strong>{{ $nteTemplate->name }}</strong>
                    @else
                        the Notice to Explain template (configure one in Company Documents)
                    @endif
                    as a Word (.docx) attachment.
                </p>
            @endif
        </div>
        <div class="bg-gray-100 p-3 sm:p-4">
            <iframe
                src="{{ $previewUrl }}"
                title="Memo preview"
                class="block w-full rounded-lg border border-gray-200 bg-gray-200"
                style="height: min(85vh, 1600px); min-height: 900px;"
            ></iframe>
        </div>
    </div>
</div>
