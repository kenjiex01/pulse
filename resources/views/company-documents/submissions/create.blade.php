@extends('layouts.app')

@section('title', 'New: '.$form->name.' — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-back-header', [
        'backUrl' => route('company-documents.submissions.index', $form),
        'backLabel' => 'Back to submissions',
        'title' => $form->name,
        'description' => 'Fill out the memo and submit.',
    ])

    <form method="POST" action="{{ route('company-documents.submissions.store', $form) }}" enctype="multipart/form-data" class="mx-auto max-w-3xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf

        @foreach ($form->elements as $element)
            @include('company-documents.submissions._field', ['element' => $element])
        @endforeach

        <div class="flex justify-end border-t border-gray-100 pt-4">
            <button type="submit" class="btn-primary">{{ $form->submit_label ?: 'Submit' }}</button>
        </div>
    </form>
@endsection
