@php
    use App\Support\TimekeepingEmployeeProfile;

    $formContext = 'setup-employee-'.$employee->employee_id;
    $activeTab = TimekeepingEmployeeProfile::normalizeSetupTab(old('active_tab', 'timekeeping'));
@endphp

<form
    method="POST"
    action="{{ route(TimekeepingEmployeeProfile::routeName('store'), $employee->employee_id) }}"
    class="space-y-4"
    data-employee-form-tabs
    data-employee-profile-setup-form
>
    @csrf
    <input type="hidden" name="form_context" value="{{ $formContext }}">
    <input type="hidden" name="setup_employee_id" value="{{ $employee->employee_id }}">
    <input type="hidden" name="search" value="{{ request('search') }}">
    <input type="hidden" name="page" value="{{ request('page') }}">
    <input type="hidden" name="active_tab" value="{{ $activeTab }}" data-employee-active-tab>

    @include('timekeeping.employee-profile._setup-tabs-nav', ['activeTab' => $activeTab])

    <div
        class="employee-tab-panel {{ $activeTab === 'timekeeping' ? '' : 'hidden' }}"
        data-employee-tab-panel="timekeeping"
    >
        @include('timekeeping.employee-profile._setup-tab-timekeeping', [
            'employee' => $employee,
            'formOptions' => $formOptions,
        ])
    </div>

    @if (TimekeepingEmployeeProfile::showApprovalMatrix())
        <div
            class="employee-tab-panel {{ $activeTab === 'approval' ? '' : 'hidden' }}"
            data-employee-tab-panel="approval"
            data-employee-profile-lazy-panel
            data-lazy-url="{{ route(TimekeepingEmployeeProfile::routeName('approval'), $employee->employee_id) }}"
            @if ($activeTab !== 'approval') data-lazy-pending="true" @endif
        >
            @if ($activeTab === 'approval')
                <div class="py-6 text-center text-sm text-gray-500">Loading approval settings…</div>
            @else
                <div class="py-6 text-center text-sm text-gray-500">Open this tab to load approval settings.</div>
            @endif
        </div>
    @endif

    <div
        class="employee-tab-panel {{ $activeTab === 'attendance' ? '' : 'hidden' }}"
        data-employee-tab-panel="attendance"
        data-employee-profile-lazy-panel
        data-lazy-url="{{ route(TimekeepingEmployeeProfile::routeName('attendance'), $employee->employee_id) }}"
        @if ($activeTab !== 'attendance') data-lazy-pending="true" @endif
    >
        @if ($activeTab === 'attendance')
            <div class="py-6 text-center text-sm text-gray-500">Loading attendance view…</div>
        @else
            <div class="py-6 text-center text-sm text-gray-500">Open this tab to load attendance view.</div>
        @endif
    </div>

    <div
        class="employee-tab-panel {{ $activeTab === 'employee-load' ? '' : 'hidden' }}"
        data-employee-tab-panel="employee-load"
        data-employee-profile-lazy-panel
        data-lazy-url="{{ route(TimekeepingEmployeeProfile::routeName('employee-load'), $employee->employee_id) }}"
        @if ($activeTab !== 'employee-load') data-lazy-pending="true" @endif
    >
        @if ($activeTab === 'employee-load')
            <div class="py-6 text-center text-sm text-gray-500">Loading employee load…</div>
        @else
            <div class="py-6 text-center text-sm text-gray-500">Open this tab to load employee load.</div>
        @endif
    </div>
</form>
