<?php

namespace App\Http\Controllers;

use App\Models\TimeCaptureFormat as TimeCaptureFormatModel;
use App\Services\SysLogService;
use App\Support\LiveTable;
use App\Support\TimeCaptureFormat as TimeCaptureFormatSupport;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimeCapturingSettingsController extends Controller
{
    public function index(Request $request): View
    {
        TimeCaptureFormatSupport::authorize($request->user(), 'view');

        $search = $request->string('search')->trim()->toString();

        $records = TimeCaptureFormatSupport::listQuery()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('device_name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->paginate(LiveTable::perPage($request, 15))
            ->withQueryString();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: TimeCaptureFormatSupport::LOG_TABLE,
                description: 'Viewed Time Capture Format list ('.$records->total().' records)',
            );
        }

        $viewData = [
            'moduleTab' => 'time-capturing-settings',
            'tabs' => TimekeepingPolicySupport::moduleTabs(),
            'moduleConfig' => TimekeepingPolicySupport::moduleTabConfig('time-capturing-settings'),
            'search' => $search,
            'records' => $records,
            'openEditId' => old('edit_timecapture_format_id', $request->input('edit')),
            'openCreate' => ($request->session()->get('errors')?->any() && old('form_context') === 'create-time-capture-format') || $request->boolean('create'),
        ];

        if ($request->ajax()) {
            return view('timekeeping.time-capture-formats._results', $viewData);
        }

        return view('timekeeping.policy.index', $viewData);
    }

    public function storeFormat(Request $request): RedirectResponse
    {
        TimeCaptureFormatSupport::authorize($request->user(), 'add');

        $validated = TimeCaptureFormatSupport::validate($request->all());
        $record = TimeCaptureFormatModel::query()->create(TimeCaptureFormatSupport::headerPayload($validated));
        TimeCaptureFormatSupport::syncFields($record, $validated);

        SysLogService::record(
            action: 'create',
            table: TimeCaptureFormatSupport::LOG_TABLE,
            recordId: $record->timecapture_format_id,
            newValues: $record->fresh('fields')->toArray(),
            description: 'Created Time Capture Format: '.TimeCaptureFormatSupport::recordLabel($record),
        );

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'time-capturing-settings'])
            ->with('success', 'Time capture format created successfully.');
    }

    public function updateFormat(Request $request, int $timeCaptureFormat): RedirectResponse
    {
        TimeCaptureFormatSupport::authorize($request->user(), 'update');

        $record = TimeCaptureFormatSupport::findOrFail($timeCaptureFormat);
        $oldValues = $record->toArray();
        $validated = TimeCaptureFormatSupport::validate($request->all(), $record->timecapture_format_id);

        $record->update(TimeCaptureFormatSupport::headerPayload($validated));
        TimeCaptureFormatSupport::syncFields($record, $validated);

        SysLogService::record(
            action: 'update',
            table: TimeCaptureFormatSupport::LOG_TABLE,
            recordId: $record->timecapture_format_id,
            oldValues: $oldValues,
            newValues: $record->fresh('fields')->toArray(),
            description: 'Updated Time Capture Format: '.TimeCaptureFormatSupport::recordLabel($record),
        );

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'time-capturing-settings'])
            ->with('success', 'Time capture format updated successfully.');
    }

    public function destroyFormat(Request $request, int $timeCaptureFormat): RedirectResponse
    {
        TimeCaptureFormatSupport::authorize($request->user(), 'delete');

        $record = TimeCaptureFormatSupport::findOrFail($timeCaptureFormat);

        if (TimeCaptureFormatSupport::isInUse($record)) {
            return redirect()
                ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'time-capturing-settings'])
                ->with('error', 'You are not allowed to delete time capture formats that are in use.');
        }

        $oldValues = $record->fresh('fields')->toArray();
        $record->delete();

        SysLogService::record(
            action: 'delete',
            table: TimeCaptureFormatSupport::LOG_TABLE,
            recordId: $record->timecapture_format_id,
            oldValues: $oldValues,
            description: 'Deleted Time Capture Format: '.TimeCaptureFormatSupport::recordLabel($record),
        );

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'time-capturing-settings'])
            ->with('success', 'Time capture format deleted successfully.');
    }
}
