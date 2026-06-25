@php
    use App\Support\TimekeepingEmployeeProfile;

    $activeTab = TimekeepingEmployeeProfile::normalizeSetupTab($activeTab ?? 'timekeeping');
@endphp

<nav class="employee-tabs-shell mb-4" role="tablist">
    @foreach (TimekeepingEmployeeProfile::setupTabs() as $tabKey => $tabLabel)
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
