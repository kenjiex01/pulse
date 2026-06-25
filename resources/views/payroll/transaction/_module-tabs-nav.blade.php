@php
    use App\Support\PayrollTransactionModule;
@endphp

<nav class="employee-tabs-shell mb-4" role="tablist">
    @foreach ($moduleTabs as $tabKey => $tabLabel)
        <a
            href="{{ route(PayrollTransactionModule::routeName('tab'), ['tab' => $tabKey]) }}"
            role="tab"
            class="employee-tab-btn {{ ($moduleTab ?? PayrollTransactionModule::DEFAULT_TAB) === $tabKey ? 'employee-tab-btn-active' : '' }}"
            aria-selected="{{ ($moduleTab ?? PayrollTransactionModule::DEFAULT_TAB) === $tabKey ? 'true' : 'false' }}"
        >
            {{ $tabLabel }}
        </a>
    @endforeach
</nav>
