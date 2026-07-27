@php
    use App\Support\PayrollCalendarModule;
@endphp

@if (count($moduleTabs ?? []) > 1)
    <nav class="employee-tabs-shell mb-4" role="tablist">
        @foreach ($moduleTabs as $tabKey => $tabLabel)
            @php
                $tabUrl = route(PayrollCalendarModule::routeName('pay-type'), [
                    'payType' => $payTypeSlug ?? PayrollCalendarModule::defaultPayTypeSlug(),
                    'year' => $year ?? date('Y'),
                ]);
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
@endif
