@php
    $activeTab = $moduleTab ?? 'policy';
@endphp

<div class="employee-tabs-shell mb-4">
    <nav class="flex flex-wrap gap-1" role="tablist" aria-label="Timekeeping Policy sections">
        @foreach ($tabs as $tabKey => $tabLabel)
            <a
                href="{{ route(\App\Support\TimekeepingPolicy::routeName('module'), ['tab' => $tabKey]) }}"
                role="tab"
                class="employee-tab-btn {{ $activeTab === $tabKey ? 'employee-tab-btn-active' : '' }}"
                aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}"
            >
                {{ $tabLabel }}
            </a>
        @endforeach
    </nav>
</div>
