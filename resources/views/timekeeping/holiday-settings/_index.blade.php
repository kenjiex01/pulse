@php
    use App\Support\HolidaySettings as HolidaySettingsSupport;
@endphp

@include('timekeeping.holiday-settings._sub-tabs-nav', [
    'subTab' => $subTab,
    'subTabs' => $subTabs,
    'search' => $search,
])

@include('partials.live-data-table', [
    'url' => HolidaySettingsSupport::moduleIndexRoute($subTab, array_filter(['search' => $search ?: null])),
    'search' => $search,
    'searchPlaceholder' => match ($subTab) {
        'groups' => 'Search group code or description...',
        'year' => 'Search year...',
        default => 'Search holiday code or description...',
    },
    'searchId' => "holiday-settings-search-$subTab",
    'paginator' => $records,
    'totalLabel' => strtolower($subConfig['label']),
    'results' => view("timekeeping.holiday-settings.{$subTab}._results", [
        'subTab' => $subTab,
        'records' => $records,
        'holidayOptions' => $holidayOptions,
        'openEditId' => $openEditId ?? null,
        'openYearId' => $openYearId ?? null,
    ])->render(),
])

@can('timekeeping-policy.create')
    @include('partials.modal', [
        'id' => "holiday-settings-create-$subTab",
        'title' => $subConfig['create_label'],
        'description' => $subConfig['description'],
        'panelClass' => $subTab === 'groups' ? 'modal-panel-lg' : ($subTab === 'year' ? 'modal-panel-md' : 'modal-panel-md'),
        'open' => $openCreate ?? false,
        'body' => view("timekeeping.holiday-settings.{$subTab}._form", [
            'record' => null,
            'isEdit' => false,
            'formContext' => "create-$subTab",
            'holidayOptions' => $holidayOptions,
        ])->render(),
    ])
@endcan
