@php
    $logsEnabled = (bool) old('enable_logs_tagging', $policy->enable_logs_tagging);
    $logRows = [
        ['label' => 'Raw Logs', 'tag' => 'raw_logs_tag', 'desc' => 'raw_logs_desc'],
        ['label' => 'Edited Logs', 'tag' => 'edited_logs_tag', 'desc' => 'edited_logs_desc'],
        ['label' => 'Filed Logs', 'tag' => 'filed_logs_tag', 'desc' => 'filed_logs_desc'],
        ['label' => 'Auto Logs', 'tag' => 'auto_logs_tag', 'desc' => 'auto_logs_desc'],
    ];
    $shiftRows = [
        ['label' => 'Default Shift', 'tag' => 'default_shift_tag', 'desc' => 'default_shift_desc'],
        ['label' => 'Planned Shift', 'tag' => 'planned_shift_tag', 'desc' => 'planned_shift_desc'],
        ['label' => 'Filed Shift', 'tag' => 'filed_shift_tag', 'desc' => 'filed_shift_desc'],
        ['label' => 'Edited Shift', 'tag' => 'edited_shift_tag', 'desc' => 'edited_shift_desc'],
    ];
@endphp

<form
    method="POST"
    action="{{ route(\App\Support\TimekeepingPolicy::routeName('update'), ['policy' => $policy->timekeeping_policy_id, 'tab' => 'logs-tagging']) }}"
    class="space-y-6 rounded-xl border border-gray-200 bg-white p-6"
    data-timekeeping-settings="logs-tagging"
>
    @csrf
    @method('PUT')

    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="enable_logs_tagging" value="0">
        <input type="checkbox" name="enable_logs_tagging" value="1" data-logs-tagging-toggle @checked($logsEnabled)>
        Enable
    </label>

    <div data-logs-tagging-panel @class(['space-y-8', 'opacity-50' => ! $logsEnabled])>
        @foreach ([['title' => 'Logs', 'rows' => $logRows], ['title' => 'Shift', 'rows' => $shiftRows]] as $section)
            <div>
                <h3 class="text-sm font-semibold text-gray-900">{{ $section['title'] }}</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="table-skolaris min-w-[640px]">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Tag</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section['rows'] as $row)
                                <tr>
                                    <td class="font-medium text-gray-900">{{ $row['label'] }}</td>
                                    <td>
                                        <input
                                            name="{{ $row['tag'] }}"
                                            type="text"
                                            maxlength="1"
                                            value="{{ old($row['tag'], $policy->{$row['tag']}) }}"
                                            class="form-input w-20 uppercase"
                                            data-logs-tagging-field
                                            @disabled(! $logsEnabled)
                                        >
                                        @error($row['tag'])<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </td>
                                    <td>
                                        <input
                                            name="{{ $row['desc'] }}"
                                            type="text"
                                            maxlength="45"
                                            value="{{ old($row['desc'], $policy->{$row['desc']}) }}"
                                            class="form-input"
                                            data-logs-tagging-field
                                            @disabled(! $logsEnabled)
                                        >
                                        @error($row['desc'])<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    @can('timekeeping-policy.update')
        <div class="flex justify-end border-t border-gray-100 pt-4">
            <button type="submit" class="btn-primary">Save Settings</button>
        </div>
    @endcan
</form>
