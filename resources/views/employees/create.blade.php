@extends('layouts.app')

@section('title', 'Add Employee — '.config('app.name'))

@section('content')
    @include('partials.flash')

    <div class="employee-wizard-shell">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            <div class="lg:w-72 lg:shrink-0">
                @include('employees._wizard-sidebar', compact('step', 'selectedCampus'))
            </div>

            <div class="min-w-0 flex-1">
                <div class="employee-wizard-panel">
                    @if ($step === 0)
                        @include('employees._wizard-step-campus', compact('campuses', 'selectedCampus'))
                    @elseif ($step === 1)
                        @if ($selectedCampus)
                            <div class="mb-6 rounded-lg border border-[#00A3E6]/30 bg-[#00A3E6]/5 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-[#0089c2]">Selected Campus</p>
                                <p class="mt-1 font-semibold text-gray-900">{{ $selectedCampus->campus_name }}</p>
                                <p class="text-sm text-gray-600">{{ $selectedCampus->campus_code }}</p>
                            </div>
                        @endif
                        @include('employees._form', [
                            'employee' => $employee,
                            'wizardMode' => true,
                        ])
                    @else
                        @include('employees._wizard-step-review', compact('selectedCampus', 'wizardData', 'roles'))
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
