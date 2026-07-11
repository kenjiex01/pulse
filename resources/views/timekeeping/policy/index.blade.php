@extends('layouts.app')

@php
    use App\Support\ShiftCode as ShiftCodeSupport;

    $moduleTab = $moduleTab ?? 'policy';
    $isPolicyList = $moduleTab === 'policy' && ! isset($tab);
    $isShiftCodeList = $moduleTab === 'shift-codes' && ! isset($tab);
    $isTimeCapturingSettings = $moduleTab === 'time-capturing-settings' && ! isset($tab);
    $isHolidaySettings = $moduleTab === 'holiday-settings' && ! isset($tab);
    $pageDescription = match (true) {
        $isPolicyList => 'Manage timekeeping policies for your organization.',
        $isShiftCodeList => 'Setup employee shift codes.',
        $isTimeCapturingSettings => 'Setup time capture formats for device uploads.',
        $isHolidaySettings => 'Manage list of holidays per year.',
        default => $moduleConfig['description'] ?? 'Configure timekeeping policy settings.',
    };
    $headerActionModalId = match (true) {
        $isPolicyList && auth()->user()->can('timekeeping-policy.create') => 'timekeeping-policy-create',
        $isShiftCodeList && auth()->user()->can('timekeeping-policy.create') => 'shift-code-create',
        $isTimeCapturingSettings && auth()->user()->can('timekeeping-policy.create') => 'time-capture-format-create',
        $isHolidaySettings && auth()->user()->can('timekeeping-policy.create') => 'holiday-settings-create-'.($subTab ?? 'holidays'),
        default => null,
    };
    $headerActionLabel = match (true) {
        $isShiftCodeList => 'New Shift Code',
        $isTimeCapturingSettings => 'New Time Capture Format',
        $isHolidaySettings => config('holiday_settings.sub_tabs.'.($subTab ?? 'holidays').'.create_label', 'New'),
        default => 'New Policy',
    };
@endphp

@section('title', 'Timekeeping Policy — '.config('app.name'))

@section('content')
    @include('partials.flash')
    @include('partials.page-header', [
        'title' => 'Timekeeping Policy',
        'description' => $pageDescription,
        'actionModalId' => $headerActionModalId,
        'actionLabel' => $headerActionLabel,
        'actionIcon' => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
    ])

    @if (! isset($tab))
        @include('timekeeping.policy._module-tabs-nav', [
            'moduleTab' => $moduleTab,
            'tabs' => $tabs,
        ])

        @if ($moduleTab === 'policy')
            @include('timekeeping.policy._policy-list')
        @elseif ($moduleTab === 'shift-codes')
            @include('timekeeping.shift-codes._list')
        @elseif ($moduleTab === 'time-capturing-settings')
            @include('timekeeping.time-capture-formats._list')
        @elseif ($moduleTab === 'holiday-settings')
            @include('timekeeping.holiday-settings._index')
        @else
            @include('timekeeping.policy._module-placeholder', [
                'moduleConfig' => $moduleConfig,
            ])
        @endif
    @else
        <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-600">
            <a href="{{ route(\App\Support\TimekeepingPolicy::routeName('module'), ['tab' => 'policy']) }}" class="font-medium text-[#0089c2] hover:underline">Policy</a>
            <span aria-hidden="true">/</span>
            <span class="font-medium text-gray-900">{{ $policy->policy_name }}</span>
            <span class="text-gray-400">({{ $policy->policy_code }})</span>
        </div>

        <div class="employee-tabs-shell mb-4">
            <nav class="flex flex-wrap gap-1" role="tablist" aria-label="Policy settings">
                @foreach (\App\Support\TimekeepingPolicy::settingsTabs() as $tabKey => $tabLabel)
                    <a
                        href="{{ route(\App\Support\TimekeepingPolicy::routeName('tab'), ['policy' => $policy->timekeeping_policy_id, 'tab' => $tabKey]) }}"
                        role="tab"
                        class="employee-tab-btn {{ $tab === $tabKey ? 'employee-tab-btn-active' : '' }}"
                        aria-selected="{{ $tab === $tabKey ? 'true' : 'false' }}"
                    >
                        {{ $tabLabel }}
                    </a>
                @endforeach
            </nav>
        </div>

        <div data-timekeeping-policy-root>
            @switch($tab)
                @case('tardiness-undertime')
                    @include('timekeeping.policy._settings-tardiness-undertime')
                    @break
                @case('overtime')
                    @include('timekeeping.policy._settings-overtime')
                    @break
                @case('breaks')
                    @include('timekeeping.policy._settings-breaks')
                    @break
                @case('leaves-absences')
                    @include('timekeeping.policy._settings-leaves-absences')
                    @break
                @case('night-differential')
                    @include('timekeeping.policy._settings-night-differential')
                    @break
                @case('general')
                    @include('timekeeping.policy._settings-general')
                    @break
                @case('team-settings')
                    @include('timekeeping.policy._settings-team-settings')
                    @break
                @case('logs-tagging')
                    @include('timekeeping.policy._settings-logs-tagging')
                    @break
                @default
                    @abort(404)
            @endswitch
        </div>
    @endif
@endsection
