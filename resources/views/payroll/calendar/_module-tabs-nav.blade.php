@php
    use App\Support\PayrollCalendarModule;
@endphp

<nav class="employee-tabs-shell mb-4" role="tablist">
    @foreach ($moduleTabs as $tabKey => $tabLabel)
        @php
            $tabUrl = $tabKey === 'calendar'
                ? route(PayrollCalendarModule::routeName('pay-type'), ['payType' => $payTypeSlug ?? PayrollCalendarModule::defaultPayTypeSlug(), 'year' => $year ?? date('Y')])
                : route(PayrollCalendarModule::routeName('priority'));
        @endphp
        <a
            href="{{ $tabUrl }}"
            role="tab"
            class="employee-tab-btn {{ ($moduleTab ?? 'calendar') === $tabKey ? 'employee-tab-btn-active' : '' }}"
            aria-selected="{{ ($moduleTab ?? 'calendar') === $tabKey ? 'true' : 'false' }}"
        >
            {{ $tabLabel }}
        </a>
    @endforeach
</nav>
