@extends('layouts.app')

@section('title', 'Document Approvals — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Document Approvals',
        'description' => 'Review memo submissions assigned to you for approval.',
    ])

    @if ($pending->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-sm font-medium text-gray-900">No pending approvals</p>
            <p class="mt-1 text-xs text-gray-500">Submissions routed to you will appear here.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($pending as $approval)
                @php
                    $submission = $approval->submission;
                    $form = $submission?->form;
                @endphp
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $form?->name ?: 'Company document' }}</p>
                            <p class="text-xs text-gray-500">
                                Submission #{{ $submission?->submission_id }} · Step {{ $approval->step_number }} — {{ $approval->step_name }}
                            </p>
                            <p class="mt-1 text-sm text-gray-600">
                                From {{ $submission?->submitter?->name ?: 'Unknown' }}
                            </p>
                        </div>
                        <a href="{{ route('company-documents.submissions.show', [$form, $submission]) }}" class="btn-secondary" data-no-loader>View submission</a>
                    </div>

                    <form method="POST" action="{{ route('company-documents.approvals.act', $submission) }}" enctype="multipart/form-data" class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                        @csrf
                        <input type="hidden" name="submission_approval_id" value="{{ $approval->submission_approval_id }}">
                        <textarea name="comment" rows="2" class="form-input w-full" placeholder="Optional comment"></textarea>
                        <div>
                            <label class="form-label">E-signature (optional)</label>
                            @include('company-documents.submissions._signature-pad', [
                                'inputName' => 'signature',
                                'required' => false,
                            ])
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" name="action" value="approve" class="btn-primary">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn-secondary">Reject</button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
@endsection
