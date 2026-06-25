@php
    use App\Support\PayrollCalendarModule;
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <nav class="employee-tabs-shell" role="tablist">
        @foreach ($payTypeTabs as $tabPayTypeId => $tabLabel)
            @php $slug = PayrollCalendarModule::payTypeSlugFromId($tabPayTypeId); @endphp
            <a
                href="{{ route(PayrollCalendarModule::routeName('pay-type'), ['payType' => $slug, 'year' => $year]) }}"
                role="tab"
                class="employee-tab-btn {{ $payTypeSlug === $slug ? 'employee-tab-btn-active' : '' }}"
                aria-selected="{{ $payTypeSlug === $slug ? 'true' : 'false' }}"
            >
                {{ $tabLabel }}
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route(PayrollCalendarModule::routeName('pay-type'), ['payType' => $payTypeSlug]) }}" class="flex items-center gap-2">
        <label for="payroll-calendar-year" class="text-sm text-gray-600">Select Year:</label>
        <select id="payroll-calendar-year" name="year" class="form-input !w-auto !py-1.5 text-sm" onchange="this.form.submit()">
            @foreach ($years as $yearOption)
                <option value="{{ $yearOption }}" @selected($yearOption === $year)>{{ $yearOption }}</option>
            @endforeach
        </select>
    </form>
</div>
