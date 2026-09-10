@extends('layouts.app')

@section('title', 'Submissions: '.$form->name.' — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-back-header', [
        'backUrl' => route('company-documents.index'),
        'backLabel' => 'Back to Company Documents',
        'title' => $form->name.' — Submissions',
        'description' => 'Review submitted memos for this template.',
    ])

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="table-skolaris min-w-full">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Submitted by</th>
                    <th>Status</th>
                    <th>Submitted at</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($submissions as $submission)
                    <tr>
                        <td class="font-medium text-gray-900">#{{ $submission->submission_id }}</td>
                        <td class="text-gray-600">{{ $submission->submitter?->name ?: '—' }}</td>
                        <td><span class="capitalize badge-muted">{{ str_replace('_', ' ', $submission->status) }}</span></td>
                        <td class="text-gray-600">{{ $submission->submitted_at?->format('M j, Y g:i A') ?: '—' }}</td>
                        <td class="text-right">
                            <a href="{{ route('company-documents.submissions.show', [$form, $submission]) }}" class="btn-icon" title="View" data-no-loader>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-10 text-center text-sm text-gray-500">No submissions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $submissions->links() }}</div>
@endsection
