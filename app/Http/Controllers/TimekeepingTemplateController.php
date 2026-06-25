<?php

namespace App\Http\Controllers;

use App\Models\TimekeepingTemplate as TimekeepingTemplateModel;
use App\Services\SysLogService;
use App\Support\LiveTable;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use App\Support\TimekeepingTemplate as TimekeepingTemplateSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimekeepingTemplateController extends Controller
{
    public function index(Request $request): View
    {
        TimekeepingTemplateSupport::authorize($request->user(), 'view');

        $search = $request->string('search')->trim()->toString();

        $records = TimekeepingTemplateSupport::listQuery()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('content', 'like', '%'.$search.'%')
                        ->orWhere('timekeeping_template_id', 'like', '%'.$search.'%')
                        ->orWhereHas('templateType', function ($typeQuery) use ($search) {
                            $typeQuery->where('template', 'like', '%'.$search.'%');
                        });
                });
            })
            ->paginate(LiveTable::perPage($request, 15))
            ->withQueryString();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: TimekeepingTemplateSupport::LOG_TABLE,
                description: 'Viewed Timekeeping Template list ('.$records->total().' records)',
            );
        }

        $viewData = [
            'moduleTab' => 'templates',
            'tabs' => TimekeepingPolicySupport::moduleTabs(),
            'moduleConfig' => TimekeepingPolicySupport::moduleTabConfig('templates'),
            'records' => $records,
            'search' => $search,
            'templateTypes' => TimekeepingTemplateSupport::templateTypeOptions(),
            'openEditId' => old('edit_timekeeping_template_id', $request->input('edit')),
            'openCreate' => ($request->session()->get('errors')?->any() && old('form_context') === 'create-timekeeping-template') || $request->boolean('create'),
        ];

        if ($request->ajax()) {
            return view('timekeeping.templates._results', $viewData);
        }

        return view('timekeeping.policy.index', $viewData);
    }

    public function store(Request $request): RedirectResponse
    {
        TimekeepingTemplateSupport::authorize($request->user(), 'add');

        $validated = TimekeepingTemplateSupport::validate($request->all());
        $record = TimekeepingTemplateModel::query()->create(TimekeepingTemplateSupport::headerPayload($validated));

        SysLogService::record(
            action: 'create',
            table: TimekeepingTemplateSupport::LOG_TABLE,
            recordId: $record->timekeeping_template_id,
            newValues: $record->fresh('templateType')->toArray(),
            description: 'Created Timekeeping Template: '.TimekeepingTemplateSupport::recordLabel($record),
        );

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'templates'])
            ->with('success', 'Template created successfully.');
    }

    public function update(Request $request, int $timekeepingTemplate): RedirectResponse
    {
        TimekeepingTemplateSupport::authorize($request->user(), 'update');

        $record = TimekeepingTemplateSupport::findOrFail($timekeepingTemplate);
        $oldValues = $record->fresh('templateType')->toArray();
        $validated = TimekeepingTemplateSupport::validate($request->all(), $record->timekeeping_template_id);

        $record->update(TimekeepingTemplateSupport::headerPayload($validated));

        SysLogService::record(
            action: 'update',
            table: TimekeepingTemplateSupport::LOG_TABLE,
            recordId: $record->timekeeping_template_id,
            oldValues: $oldValues,
            newValues: $record->fresh('templateType')->toArray(),
            description: 'Updated Timekeeping Template: '.TimekeepingTemplateSupport::recordLabel($record),
        );

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'templates'])
            ->with('success', 'Template updated successfully.');
    }

    public function toggleStatus(Request $request, int $timekeepingTemplate): RedirectResponse
    {
        TimekeepingTemplateSupport::authorize($request->user(), 'update');

        $record = TimekeepingTemplateSupport::findOrFail($timekeepingTemplate);
        $oldValues = $record->fresh('templateType')->toArray();

        if (! $record->is_active) {
            $conflict = TimekeepingTemplateSupport::activeConflictMessage($record);

            if ($conflict) {
                return redirect()
                    ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'templates'])
                    ->with('error', $conflict);
            }
        }

        $record->update(['is_active' => ! $record->is_active]);

        SysLogService::record(
            action: 'update',
            table: TimekeepingTemplateSupport::LOG_TABLE,
            recordId: $record->timekeeping_template_id,
            oldValues: $oldValues,
            newValues: $record->fresh('templateType')->toArray(),
            description: 'Toggled Timekeeping Template status: '.TimekeepingTemplateSupport::recordLabel($record),
        );

        $statusLabel = $record->is_active ? 'active' : 'inactive';

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'templates'])
            ->with('success', 'Template marked as '.$statusLabel.'.');
    }

    public function destroy(Request $request, int $timekeepingTemplate): RedirectResponse
    {
        TimekeepingTemplateSupport::authorize($request->user(), 'delete');

        $record = TimekeepingTemplateSupport::findOrFail($timekeepingTemplate);

        if (TimekeepingTemplateSupport::isActive($record)) {
            return redirect()
                ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'templates'])
                ->with('error', 'Only inactive templates can be deleted. Deactivate the template first.');
        }

        $oldValues = $record->fresh('templateType')->toArray();
        $record->delete();

        SysLogService::record(
            action: 'delete',
            table: TimekeepingTemplateSupport::LOG_TABLE,
            recordId: $record->timekeeping_template_id,
            oldValues: $oldValues,
            description: 'Deleted Timekeeping Template: '.TimekeepingTemplateSupport::recordLabel($record),
        );

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('module'), ['tab' => 'templates'])
            ->with('success', 'Template deleted successfully.');
    }
}
