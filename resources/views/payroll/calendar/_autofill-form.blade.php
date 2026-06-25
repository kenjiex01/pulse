@php
    use App\Models\PayType;
    use App\Support\PayrollCalendarModule;
@endphp

<form
    method="POST"
    action="{{ route(PayrollCalendarModule::routeName('autofill'), ['payType' => $payTypeSlug]) }}"
    class="space-y-4"
    data-payroll-calendar-autofill
    data-pay-type-id="{{ $payTypeId }}"
>
    @csrf
    <input type="hidden" name="form_context" value="{{ $formContext }}">

    @if ($payTypeId === PayType::WEEKLY)
        <div>
            <label class="form-label" for="autofill-week-day">Every Week on <span class="text-red-500">*</span></label>
            <select id="autofill-week-day" name="week_day" class="form-input" required>
                @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $index => $dayLabel)
                    <option value="{{ $index + 1 }}" @selected((string) old('week_day', '1') === (string) ($index + 1))>{{ $dayLabel }}</option>
                @endforeach
            </select>
            @error('week_day')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    @endif

    @if ($payTypeId === PayType::SEMI_MONTHLY)
        <div>
            <label class="form-label">Frequency <span class="text-red-500">*</span></label>
            <div class="flex flex-wrap items-center gap-2">
                <select name="frequency_day_1" class="form-input !w-auto" required>
                    @for ($day = 1; $day <= 30; $day++)
                        <option value="{{ $day }}" @selected((string) old('frequency_day_1', '15') === (string) $day)>{{ $day }}</option>
                    @endfor
                </select>
                <span class="text-sm text-gray-600">and</span>
                <select name="frequency_day_2" class="form-input !w-auto">
                    @for ($day = 1; $day <= 30; $day++)
                        <option value="{{ $day }}" @selected((string) old('frequency_day_2') === (string) $day)>{{ $day }}</option>
                    @endfor
                    <option value="last" @selected(old('frequency_day_2') === 'last')>Last</option>
                </select>
                <span class="text-sm text-gray-600">day of every month</span>
            </div>
            @error('frequency_day_1')<p class="form-error">{{ $message }}</p>@enderror
            @error('frequency_day_2')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    @endif

    @if ($payTypeId === PayType::MONTHLY)
        <div>
            <label class="form-label" for="autofill-frequency-day">Frequency</label>
            <select id="autofill-frequency-day" name="frequency_day" class="form-input">
                @for ($day = 1; $day <= 30; $day++)
                    <option value="{{ $day }}" @selected((string) old('frequency_day') === (string) $day)>{{ $day }}</option>
                @endfor
                <option value="last" @selected(old('frequency_day') === 'last' || old('frequency_day') === null)>Last</option>
            </select>
            @error('frequency_day')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    @endif

    <div>
        <label class="form-label" for="autofill-pay-year">Pay Year <span class="text-red-500">*</span></label>
        <input id="autofill-pay-year" type="number" name="pay_year" class="form-input" min="1" maxlength="4" required value="{{ old('pay_year', $year) }}">
        @error('pay_year')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label" for="autofill-date-from">Date From <span class="text-red-500">*</span></label>
        <input id="autofill-date-from" type="date" name="date_from" class="form-input" required value="{{ old('date_from') }}">
        @error('date_from')<p class="form-error">{{ $message }}</p>@enderror
    </div>

    @if ($payTypeId === PayType::DAILY)
        <div>
            <label class="form-label" for="autofill-date-to">Date To</label>
            <input id="autofill-date-to" type="date" name="date_to" class="form-input" value="{{ old('date_to') }}">
            @error('date_to')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label" for="autofill-occurrences">Occurrences</label>
            <input id="autofill-occurrences" type="number" name="occurrences" class="form-input" min="1" value="{{ old('occurrences') }}">
            @error('occurrences')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    @else
        <div>
            <label class="form-label">Generate Until</label>
            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="range_mode" value="date_to" @checked(old('range_mode', 'date_to') === 'date_to')>
                    <span>Date To</span>
                </label>
                <input type="date" name="date_to" class="form-input" value="{{ old('date_to') }}">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="range_mode" value="occurrences" @checked(old('range_mode') === 'occurrences')>
                    <span>Occurrences</span>
                </label>
                <input type="number" name="occurrences" class="form-input" min="1" value="{{ old('occurrences') }}">
            </div>
            @error('date_to')<p class="form-error">{{ $message }}</p>@enderror
            @error('occurrences')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    @endif

    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="is_regular_period" value="1" @checked(old('is_regular_period', true))>
        <span>Regular Period</span>
    </label>

    @include('partials.modal-form-actions', ['submitLabel' => 'Generate'])
</form>
