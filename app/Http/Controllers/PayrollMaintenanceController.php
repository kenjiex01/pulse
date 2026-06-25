<?php

namespace App\Http\Controllers;

use App\Services\SysLogService;
use App\Support\LiveTable;
use App\Support\PayrollMaintenance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollMaintenanceController extends Controller
{
    private function rejectProtectedMutation(string $tab, $model): ?RedirectResponse
    {
        if (! PayrollMaintenance::isProtectedRecord($model, $tab)) {
            return null;
        }

        return redirect()
            ->route(PayrollMaintenance::routeName('tab'), ['tab' => $tab])
            ->with('error', PayrollMaintenance::protectedRecordErrorMessage($tab));
    }

    public function index(Request $request, string $tab): View
    {
        $tab = PayrollMaintenance::resolveTab($tab);
        PayrollMaintenance::authorize($request->user(), 'view');

        $config = PayrollMaintenance::config($tab);
        $search = $request->string('search')->trim()->toString();

        $records = PayrollMaintenance::modelQuery($tab)
            ->when($search !== '', function ($query) use ($config, $search) {
                $query->where(function ($searchQuery) use ($config, $search) {
                    foreach ($config['search'] as $column) {
                        $searchQuery->orWhere($column, 'like', '%'.$search.'%');
                    }
                });
            })
            ->paginate(LiveTable::perPage($request, 10))
            ->withQueryString();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: $config['log_table'],
                description: 'Viewed '.$config['name'].' list ('.$records->total().' records)',
            );
        }

        $viewData = [
            'tab' => $tab,
            'tabs' => PayrollMaintenance::tabs(),
            'config' => $config,
            'records' => $records,
            'search' => $search,
            'selectOptions' => PayrollMaintenance::selectOptions(),
            'openEditId' => $request->input('edit'),
        ];

        if ($request->ajax()) {
            return view('payroll.maintenance-table._results', $viewData);
        }

        return view('payroll.maintenance-table.index', $viewData);
    }

    public function store(Request $request, string $tab): RedirectResponse
    {
        $tab = PayrollMaintenance::resolveTab($tab);
        PayrollMaintenance::authorize($request->user(), 'add');

        $config = PayrollMaintenance::config($tab);
        $validated = $request->validate(PayrollMaintenance::validationRules($tab));
        $payload = PayrollMaintenance::validatedPayload($tab, $validated);

        $record = $config['model']::query()->create($payload);

        SysLogService::record(
            action: 'create',
            table: $config['log_table'],
            recordId: $record->getKey(),
            newValues: $record->fresh()->toArray(),
            description: 'Created '.$config['name'].' record: '.PayrollMaintenance::recordLabel($record, $tab),
        );

        return redirect()
            ->route(PayrollMaintenance::routeName('tab'), ['tab' => $tab])
            ->with('success', $config['name'].' record created successfully.');
    }

    public function update(Request $request, string $tab, int $record): RedirectResponse
    {
        $tab = PayrollMaintenance::resolveTab($tab);
        PayrollMaintenance::authorize($request->user(), 'update');

        $config = PayrollMaintenance::config($tab);
        $model = PayrollMaintenance::findOrFail($tab, $record);

        if ($redirect = $this->rejectProtectedMutation($tab, $model)) {
            return $redirect;
        }

        $oldValues = $model->toArray();

        $validated = $request->validate(PayrollMaintenance::validationRules($tab, $model));
        $payload = PayrollMaintenance::validatedPayload($tab, $validated);

        $model->update($payload);

        SysLogService::record(
            action: 'update',
            table: $config['log_table'],
            recordId: $model->getKey(),
            oldValues: $oldValues,
            newValues: $model->fresh()->toArray(),
            description: 'Updated '.$config['name'].' record: '.PayrollMaintenance::recordLabel($model, $tab),
        );

        return redirect()
            ->route(PayrollMaintenance::routeName('tab'), ['tab' => $tab])
            ->with('success', $config['name'].' record updated successfully.');
    }

    public function toggleStatus(Request $request, string $tab, int $record): RedirectResponse
    {
        $tab = PayrollMaintenance::resolveTab($tab);
        PayrollMaintenance::authorize($request->user(), 'update');

        $config = PayrollMaintenance::config($tab);
        $model = PayrollMaintenance::findOrFail($tab, $record);

        if ($redirect = $this->rejectProtectedMutation($tab, $model)) {
            return $redirect;
        }

        $oldValues = $model->toArray();

        $model->update(['is_active' => ! $model->is_active]);

        SysLogService::record(
            action: 'update',
            table: $config['log_table'],
            recordId: $model->getKey(),
            oldValues: $oldValues,
            newValues: $model->fresh()->toArray(),
            description: 'Toggled '.$config['name'].' status: '.PayrollMaintenance::recordLabel($model, $tab),
        );

        $statusLabel = $model->is_active ? 'active' : 'inactive';

        return redirect()
            ->route(PayrollMaintenance::routeName('tab'), ['tab' => $tab])
            ->with('success', $config['name'].' marked as '.$statusLabel.'.');
    }

    public function destroy(Request $request, string $tab, int $record): RedirectResponse
    {
        $tab = PayrollMaintenance::resolveTab($tab);
        PayrollMaintenance::authorize($request->user(), 'delete');

        $config = PayrollMaintenance::config($tab);
        $model = PayrollMaintenance::findOrFail($tab, $record);

        if ($redirect = $this->rejectProtectedMutation($tab, $model)) {
            return $redirect;
        }

        if ($model->is_active) {
            return redirect()
                ->route(PayrollMaintenance::routeName('index'), ['tab' => $tab])
                ->with('error', 'Only inactive records can be deleted. Deactivate the record first.');
        }

        $oldValues = $model->toArray();
        $label = PayrollMaintenance::recordLabel($model, $tab);
        $recordId = $model->getKey();

        $model->delete();

        SysLogService::record(
            action: 'delete',
            table: $config['log_table'],
            recordId: $recordId,
            oldValues: $oldValues,
            description: 'Deleted '.$config['name'].' record: '.$label,
        );

        return redirect()
            ->route(PayrollMaintenance::routeName('tab'), ['tab' => $tab])
            ->with('success', $config['name'].' record deleted successfully.');
    }
}
