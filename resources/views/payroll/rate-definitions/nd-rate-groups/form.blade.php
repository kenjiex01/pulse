@extends('layouts.app')

@section('title', ($ndRateGroup ? 'Edit' : 'Create').' Night Diff. Rate Group — '.config('app.name'))

@section('content')
    @include('partials.flash')

    <div class="mb-6">
        <a href="{{ route('payroll.rate-definitions.tab', ['tab' => 'nd-rate-groups']) }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to Night Diff. Rate Groups
        </a>
        <h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ $ndRateGroup ? 'Edit' : 'Create' }} Night Diff. Rate Group</h1>
        <p class="mt-1 text-sm text-gray-500">Set the night differential rate group details, time range, and rates per day type.</p>
    </div>

    <form
        method="POST"
        action="{{ $ndRateGroup ? route('payroll.rate-definitions.nd-rate-groups.update', $ndRateGroup->nd_rate_group_id) : route('payroll.rate-definitions.nd-rate-groups.store') }}"
        class="space-y-6"
        data-rate-definition-form
    >
        @csrf
        @if ($ndRateGroup)
            @method('PUT')
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="nd_rate_group_code" class="form-label">Night Diff. Rate Group Code</label>
                    <input id="nd_rate_group_code" name="nd_rate_group_code" type="text" maxlength="4" value="{{ old('nd_rate_group_code', $ndRateGroup?->nd_rate_group_code) }}" class="form-input uppercase" required>
                    @error('nd_rate_group_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="form-label">Description</label>
                    <input id="description" name="description" type="text" maxlength="45" value="{{ old('description', $ndRateGroup?->description) }}" class="form-input" required>
                    @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="rate_basis_id" class="form-label">Rate Basis</label>
                    <select id="rate_basis_id" name="rate_basis_id" class="form-input" data-rate-basis-select required>
                        <option value="">Select Rate Basis</option>
                        @foreach ($selectOptions['rate_basis'] ?? [] as $optionValue => $optionLabel)
                            <option value="{{ $optionValue }}" @selected((string) old('rate_basis_id', $ndRateGroup?->rate_basis_id) === (string) $optionValue)>{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                    @error('rate_basis_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">Time</label>
                    <div class="flex items-center gap-2">
                        <input type="time" name="tm_start" value="{{ old('tm_start', $ndRateGroup?->tm_start ?: '22:00') }}" class="form-input" required>
                        <span class="text-gray-400">–</span>
                        <input type="time" name="tm_end" value="{{ old('tm_end', $ndRateGroup?->tm_end ?: '06:00') }}" class="form-input" required>
                    </div>
                    @error('tm_start')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('tm_end')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @include('payroll.rate-definitions._rates-grid', [
                'group' => $ndRateGroup,
                'dayTypes' => $dayTypes,
                'timeTypes' => $timeTypes,
                'existingRates' => $existingRates,
                'selectOptions' => $selectOptions,
            ])
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('payroll.rate-definitions.tab', ['tab' => 'nd-rate-groups']) }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Save</button>
        </div>
    </form>
@endsection
