@php
    use App\Support\TimeLogs as TimeLogsSupport;
@endphp

<div class="employee-tabs-shell mb-4">
    <nav class="flex flex-wrap gap-1" role="tablist" aria-label="Teaching load source">
        <a
            href="{{ route(TimeLogsSupport::routeName('tab'), ['tab' => TimeLogsSupport::TEACHING_LOADS_TAB, 'load_source' => TimeLogsSupport::LOAD_SOURCE_SKOLARIS, 'search' => request('search') ?: null]) }}"
            role="tab"
            class="employee-tab-btn {{ ($loadSource ?? TimeLogsSupport::LOAD_SOURCE_SKOLARIS) === TimeLogsSupport::LOAD_SOURCE_SKOLARIS ? 'employee-tab-btn-active' : '' }}"
            aria-selected="{{ ($loadSource ?? TimeLogsSupport::LOAD_SOURCE_SKOLARIS) === TimeLogsSupport::LOAD_SOURCE_SKOLARIS ? 'true' : 'false' }}"
        >
            Skolaris Pulls
        </a>
        <a
            href="{{ route(TimeLogsSupport::routeName('tab'), ['tab' => TimeLogsSupport::TEACHING_LOADS_TAB, 'load_source' => TimeLogsSupport::LOAD_SOURCE_UPLOADED, 'search' => request('search') ?: null, 'parse_status' => request('parse_status') ?: null]) }}"
            role="tab"
            class="employee-tab-btn {{ ($loadSource ?? '') === TimeLogsSupport::LOAD_SOURCE_UPLOADED ? 'employee-tab-btn-active' : '' }}"
            aria-selected="{{ ($loadSource ?? '') === TimeLogsSupport::LOAD_SOURCE_UPLOADED ? 'true' : 'false' }}"
        >
            Uploaded PDFs
        </a>
    </nav>
</div>
