@php
    use App\Support\PayrollCalendarModule;
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route(PayrollCalendarModule::routeName('update'), ['payType' => $payTypeSlug, 'period' => $period->payroll_calendar_id]) : route(PayrollCalendarModule::routeName('store'), ['payType' => $payTypeSlug]) }}"
    class="space-y-4"
    data-payroll-calendar-form
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <input type="hidden" name="form_context" value="{{ $formContext }}">
    @if ($isEdit)
        <input type="hidden" name="edit_period_id" value="{{ $period->payroll_calendar_id }}">
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label" for="{{ $fieldIdPrefix ?? '' }}pay_year">Pay Year <span class="text-red-500">*</span></label>
            <input
                id="{{ $fieldIdPrefix ?? '' }}pay_year"
                type="number"
                name="pay_year"
                class="form-input"
                min="1"
                maxlength="4"
                required
                value="{{ old('pay_year', $period?->pay_year ?? $year) }}"
            >
            @error('pay_year')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label" for="{{ $fieldIdPrefix ?? '' }}pay_period">Pay Period <span class="text-red-500">*</span></label>
            <input
                id="{{ $fieldIdPrefix ?? '' }}pay_period"
                type="number"
                name="pay_period"
                class="form-input"
                min="1"
                max="999"
                required
                value="{{ old('pay_period', $period?->pay_period ?? $nextPayPeriod) }}"
            >
            @error('pay_period')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label" for="{{ $fieldIdPrefix ?? '' }}date_from">Date From <span class="text-red-500">*</span></label>
            <input
                id="{{ $fieldIdPrefix ?? '' }}date_from"
                type="date"
                name="date_from"
                class="form-input"
                required
                value="{{ old('date_from', isset($period) ? $period->dt_from->format('Y-m-d') : '') }}"
            >
            @error('date_from')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label" for="{{ $fieldIdPrefix ?? '' }}date_to">Date To <span class="text-red-500">*</span></label>
            <input
                id="{{ $fieldIdPrefix ?? '' }}date_to"
                type="date"
                name="date_to"
                class="form-input"
                required
                value="{{ old('date_to', isset($period) ? $period->dt_to->format('Y-m-d') : '') }}"
            >
            @error('date_to')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label class="form-label" for="{{ $fieldIdPrefix ?? '' }}calendar_month">Calendar Month</label>
        <select id="{{ $fieldIdPrefix ?? '' }}calendar_month" name="calendar_month" class="form-input">
            <option value="">Auto-Assign</option>
            @foreach ($months as $monthNumber => $monthLabel)
                <option value="{{ $monthNumber }}" @selected((string) old('calendar_month', $period?->calendar_month ?? '') === (string) $monthNumber)>
                    {{ $monthLabel }}
                </option>
            @endforeach
        </select>
        @error('calendar_month')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input
            type="checkbox"
            name="is_regular_period"
            value="1"
            @checked(old('is_regular_period', $period?->is_regular_period ?? true))
        >
        <span>Regular Period</span>
    </label>

    @php
        $selectedCollegeCodes = collect(old(
            'college_codes',
            $period?->colleges
                ?->loadMissing('college')
                ?->pluck('college.college_code')
                ->filter()
                ->unique()
                ->values()
                ->all() ?? []
        ))
            ->map(fn ($code) => (string) $code)
            ->all();
        $selectedUserTypes = collect(old('user_types', $period?->userTypes?->pluck('user_type')->all() ?? []))
            ->map(fn ($type) => (string) $type)
            ->all();
        $collegesRequired = PayrollCalendarModule::requiresColleges($selectedUserTypes);
    @endphp

    <div class="space-y-4">
        <div data-pc-user-types-root>
            @include('partials.dual-list-select', [
                'name' => 'user_types[]',
                'label' => 'Category (User Type)',
                'options' => $userTypeOptions ?? [],
                'selected' => $selectedUserTypes,
                'required' => true,
                'hint' => 'Select employee categories covered by this pay period.',
            ])
            @error('user_types')<p class="form-error">{{ $message }}</p>@enderror
            @error('user_types.*')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div data-pc-colleges-wrap>
            @include('partials.dual-list-select', [
                'name' => 'college_codes[]',
                'label' => 'Colleges',
                'options' => $collegeSelect['options'] ?? [],
                'selected' => $selectedCollegeCodes,
                'required' => $collegesRequired,
                'disabled' => ! $collegesRequired,
                'size' => 10,
                'hint' => $collegesRequired
                    ? 'Select colleges covered by this pay period (all campuses for each college).'
                    : 'Colleges are not used for Admin-only pay periods.',
            ])
            @error('college_codes')<p class="form-error">{{ $message }}</p>@enderror
            @error('college_codes.*')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    </div>

    @include('partials.modal-form-actions')
</form>
