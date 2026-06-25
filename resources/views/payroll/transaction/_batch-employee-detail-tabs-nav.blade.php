@php
    use App\Support\PayrollTransactionModule;

    $activeTab = PayrollTransactionModule::resolveBatchDetailTab($activeTab ?? 'incomes');
@endphp

<nav class="employee-tabs-shell mb-4" role="tablist">
    @foreach (PayrollTransactionModule::BATCH_DETAIL_TABS as $tabKey => $tabLabel)
        <button
            type="button"
            role="tab"
            class="employee-tab-btn {{ $activeTab === $tabKey ? 'employee-tab-btn-active' : '' }}"
            data-employee-tab="{{ $tabKey }}"
            aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}"
        >
            {{ $tabLabel }}
        </button>
    @endforeach
</nav>
