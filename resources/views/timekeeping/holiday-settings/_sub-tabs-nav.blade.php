@php
    use App\Support\HolidaySettings as HolidaySettingsSupport;
@endphp

<div class="employee-tabs-shell mb-4">
    <nav class="flex flex-wrap gap-1" role="tablist" aria-label="Holiday settings">
        @foreach ($subTabs as $tabKey => $tabLabel)
            <a
                href="{{ HolidaySettingsSupport::moduleIndexRoute($tabKey, array_filter(['search' => $search ?: null])) }}"
                role="tab"
                class="employee-tab-btn {{ $subTab === $tabKey ? 'employee-tab-btn-active' : '' }}"
                aria-selected="{{ $subTab === $tabKey ? 'true' : 'false' }}"
            >
                {{ $tabLabel }}
            </a>
        @endforeach
    </nav>
</div>
