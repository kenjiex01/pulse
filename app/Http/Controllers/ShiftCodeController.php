<?php

namespace App\Http\Controllers;

use App\Models\ShiftCode as ShiftCodeModel;
use App\Services\SysLogService;
use App\Support\LiveTable;
use App\Support\ShiftCode as ShiftCodeSupport;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftCodeController extends Controller
{
    public function index(Request $request): View
    {
        ShiftCodeSupport::authorize($request->user(), 'view');

        $search = $request->string('search')->trim()->toString();

        $records = ShiftCodeSupport::listQuery()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('shift_code', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('time_in', 'like', '%'.$search.'%')
                        ->orWhere('time_out', 'like', '%'.$search.'%');
                });
            })
            ->paginate(LiveTable::perPage($request, 15))
            ->withQueryString();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: ShiftCodeSupport::LOG_TABLE,
                description: 'Viewed Shift Code list ('.$records->total().' records)',
            );
        }

        $viewData = [
            'moduleTab' => 'shift-codes',
            'tabs' => TimekeepingPolicySupport::moduleTabs(),
            'moduleConfig' => TimekeepingPolicySupport::moduleTabConfig('shift-codes'),
            'records' => $records,
            'search' => $search,
            'openEditId' => old('edit_shift_code_id', $request->input('edit')),
            'openCreate' => ($request->session()->get('errors')?->any() && old('form_context') === 'create-shift-code') || $request->boolean('create'),
        ];

        if ($request->ajax()) {
            return view('timekeeping.shift-codes._results', $viewData);
        }

        return view('timekeeping.policy.index', $viewData);
    }

    public function store(Request $request): RedirectResponse
    {
        ShiftCodeSupport::authorize($request->user(), 'add');

        $validated = ShiftCodeSupport::validate($request->all());
        $record = ShiftCodeModel::query()->create(ShiftCodeSupport::headerPayload($validated));
        ShiftCodeSupport::syncBreaks($record, ShiftCodeSupport::breaksPayload($validated));

        SysLogService::record(
            action: 'create',
            table: ShiftCodeSupport::LOG_TABLE,
            recordId: $record->shift_code_id,
            newValues: $record->fresh('breaks')->toArray(),
            description: 'Created Shift Code: '.ShiftCodeSupport::recordLabel($record),
        );

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'shift-codes'])
            ->with('success', 'Shift code created successfully.');
    }

    public function update(Request $request, int $shiftCode): RedirectResponse
    {
        ShiftCodeSupport::authorize($request->user(), 'update');

        $record = ShiftCodeSupport::findOrFail($shiftCode);
        $oldValues = $record->fresh('breaks')->toArray();
        $validated = ShiftCodeSupport::validate($request->all(), $record->shift_code_id);

        $record->update(ShiftCodeSupport::headerPayload($validated));
        ShiftCodeSupport::syncBreaks($record, ShiftCodeSupport::breaksPayload($validated));

        SysLogService::record(
            action: 'update',
            table: ShiftCodeSupport::LOG_TABLE,
            recordId: $record->shift_code_id,
            oldValues: $oldValues,
            newValues: $record->fresh('breaks')->toArray(),
            description: 'Updated Shift Code: '.ShiftCodeSupport::recordLabel($record),
        );

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'shift-codes'])
            ->with('success', 'Shift code updated successfully.');
    }

    public function destroy(Request $request, int $shiftCode): RedirectResponse
    {
        ShiftCodeSupport::authorize($request->user(), 'delete');

        $record = ShiftCodeSupport::findOrFail($shiftCode);

        if (ShiftCodeSupport::isInUse($record)) {
            return redirect()
                ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'shift-codes'])
                ->with('error', 'You are not allowed to delete shift codes that are in use.');
        }

        $oldValues = $record->fresh('breaks')->toArray();
        $record->delete();

        SysLogService::record(
            action: 'delete',
            table: ShiftCodeSupport::LOG_TABLE,
            recordId: $record->shift_code_id,
            oldValues: $oldValues,
            description: 'Deleted Shift Code: '.ShiftCodeSupport::recordLabel($record),
        );

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'shift-codes'])
            ->with('success', 'Shift code deleted successfully.');
    }
}
