@extends('layouts.app')

@section('title', 'Memo Setup — '.config('app.name'))

@section('content')
    @include('partials.flash')

    @include('partials.page-header', [
        'title' => 'Memo Setup',
        'description' => 'Choose memo templates and configure the email subject, intro body, and optional CC for each violation type. The rendered memo document is included in the email automatically.',
    ])

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <form method="POST" action="{{ route(\App\Support\TimekeepingMemoSetup::routeName('update')) }}" class="space-y-8">
            @csrf
            @method('PUT')

            @foreach (\App\Models\TimekeepingMemoSetup::TYPES as $type)
                @php
                    $setup = $setups->get($type);
                    $typeLabel = \App\Models\TimekeepingMemoSetup::labelForType($type);
                    $formField = $type.'_form_id';
                    $subjectField = $type.'_email_subject';
                    $bodyField = $type.'_email_body';
                    $ccField = $type.'_email_cc';
                @endphp
                <section class="rounded-lg border border-gray-200 p-4 sm:p-5">
                    <h2 class="mb-4 text-base font-semibold text-gray-900">{{ $typeLabel }}</h2>
                    <div class="space-y-4">
                        <div>
                            <label for="{{ $formField }}" class="form-label">{{ $typeLabel }} memo template</label>
                            <select id="{{ $formField }}" name="{{ $formField }}" class="form-input w-full max-w-xl">
                                <option value="">— Select template —</option>
                                @foreach ($memoForms as $form)
                                    <option value="{{ $form->company_document_form_id }}" @selected((int) old($formField, $setup?->company_document_form_id) === (int) $form->company_document_form_id)>
                                        {{ $form->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Templates come from Human Resource → Company Documents (memo type).</p>
                            @error($formField)
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="{{ $subjectField }}" class="form-label">Email subject <span class="text-red-600">*</span></label>
                            <input
                                type="text"
                                id="{{ $subjectField }}"
                                name="{{ $subjectField }}"
                                value="{{ old($subjectField, $setup?->email_subject) }}"
                                class="form-input w-full max-w-3xl"
                                maxlength="255"
                                required
                            >
                            @error($subjectField)
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="{{ $bodyField }}" class="form-label">Email intro body <span class="text-red-600">*</span></label>
                            <textarea
                                id="{{ $bodyField }}"
                                name="{{ $bodyField }}"
                                rows="5"
                                class="form-input w-full max-w-3xl"
                                maxlength="10000"
                                required
                            >{{ old($bodyField, $setup?->email_body) }}</textarea>
                            @error($bodyField)
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="{{ $ccField }}" class="form-label">Email CC</label>
                            <input
                                type="text"
                                id="{{ $ccField }}"
                                name="{{ $ccField }}"
                                value="{{ old($ccField, $setup?->email_cc) }}"
                                class="form-input w-full max-w-3xl"
                                maxlength="500"
                                placeholder="hr@example.com, supervisor@example.com"
                            >
                            <p class="mt-1 text-xs text-gray-500">Optional. Separate multiple addresses with commas.</p>
                            @error($ccField)
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>
            @endforeach

            <p class="text-xs text-gray-500">
                You can use merge tags in subject and intro body, e.g.
                <code class="text-gray-700">@{{employee_full_name}}</code>,
                <code class="text-gray-700">@{{count_of_lates}}</code>,
                <code class="text-gray-700">@{{late_dates}}</code>,
                <code class="text-gray-700">@{{current_date}}</code>.
                The selected memo template is rendered as a PDF attachment on send.
            </p>

            @can('memo-setup.update')
                <div class="pt-2">
                    <button type="submit" class="btn-primary">Save Setup</button>
                </div>
            @endcan
        </form>
    </div>
@endsection
