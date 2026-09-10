@extends('layouts.app')

@section('title', 'Submission #'.$submission->submission_id.' — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-back-header', [
        'backUrl' => route('company-documents.submissions.index', $form),
        'backLabel' => 'Back to submissions',
        'title' => $form->name,
        'description' => 'Submission #'.$submission->submission_id.' — '.str_replace('_', ' ', $submission->status),
    ])

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <dl class="mb-6 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Submitted by</dt>
                    <dd class="mt-1 text-gray-900">{{ $submission->submitter?->name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Submitted at</dt>
                    <dd class="mt-1 text-gray-900">{{ $submission->submitted_at?->format('M j, Y g:i A') ?: '—' }}</dd>
                </div>
            </dl>

            @php
                $valuesByKey = $submission->values->keyBy('field_key');
                $elements = collect($snapshot['elements'] ?? []);
            @endphp

            <div class="space-y-4">
                @foreach ($elements as $element)
                    @php
                        $key = $element['field_key'] ?? null;
                        $value = $key ? $valuesByKey->get($key) : null;
                    @endphp
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $element['label'] ?? $element['type'] }}</p>
                        <div class="mt-1 text-sm text-gray-900">
                            @if ($value?->file_path)
                                {{ $value->original_filename ?: 'Uploaded file' }}
                            @elseif ($value?->value_json)
                                {{ json_encode($value->value_json) }}
                            @else
                                {{ $value?->value_text ?: '—' }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($submission->submissionApprovals->isNotEmpty())
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-gray-900">Approval progress</h2>
                <div class="space-y-3">
                    @foreach ($submission->submissionApprovals as $approval)
                        <div class="flex items-start justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Step {{ $approval->step_number }} — {{ $approval->step_name }}</p>
                                <p class="text-xs text-gray-500">{{ $approval->assignee?->name }}</p>
                                @if ($approval->comment)
                                    <p class="mt-1 text-xs text-gray-600">{{ $approval->comment }}</p>
                                @endif
                            </div>
                            <span class="capitalize badge-muted">{{ $approval->status }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
