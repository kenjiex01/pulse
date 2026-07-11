<?php

namespace App\Http\Controllers;

use App\Models\GovtTableWtaxAnnual2023;
use App\Services\SysLogService;
use App\Support\GovernmentTables;
use App\Support\LiveTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GovernmentTablesController extends Controller
{
    public function index(Request $request, string $tab): View
    {
        $tab = GovernmentTables::resolveTab($tab);
        GovernmentTables::authorize($request->user(), 'view');

        $config = GovernmentTables::config($tab);
        $search = $request->string('search')->trim()->toString();
        $frequency = GovernmentTables::resolveWtax2023Frequency($request->input('frequency'));

        if (($config['type'] ?? null) === 'wtax2023') {
            return $this->wtax2023Index($request, $tab, $config, $search, $frequency);
        }

        $records = GovernmentTables::modelQuery($tab)
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
            'tabs' => GovernmentTables::tabs(),
            'config' => $config,
            'records' => $records,
            'search' => $search,
            'openEditId' => $request->input('edit'),
            'frequency' => $frequency,
        ];

        if ($request->ajax()) {
            return view('payroll.government-tables._results', $viewData);
        }

        return view('payroll.government-tables.index', $viewData);
    }

    private function wtax2023Index(Request $request, string $tab, array $config, string $search, string $frequency): View
    {
        $viewData = [
            'tab' => $tab,
            'tabs' => GovernmentTables::tabs(),
            'config' => $config,
            'search' => $search,
            'frequency' => $frequency,
            'wtaxFrequencies' => GovernmentTables::WTAX2023_FREQUENCIES,
        ];

        if ($frequency === 'annual') {
            $records = GovtTableWtaxAnnual2023::query()
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($searchQuery) use ($search) {
                        foreach (['income_from', 'income_to', 'amount_due', 'percentage_due'] as $column) {
                            $searchQuery->orWhere($column, 'like', '%'.$search.'%');
                        }
                    });
                })
                ->orderBy('income_from')
                ->paginate(LiveTable::perPage($request, 10))
                ->withQueryString();

            $viewData['records'] = $records;
            $viewData['openEditId'] = $request->input('edit');

            if ($request->ajax()) {
                return view('payroll.government-tables._wtax2023-annual-results', $viewData);
            }
        } else {
            $typeId = GovernmentTables::WTAX2023_FREQUENCIES[$frequency]['type_id'];
            $viewData['wtaxGrid'] = GovernmentTables::wtax2023Grid($typeId);
            $viewData['wtaxTypeId'] = $typeId;
        }

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: 'tbl_govt_table_wtax_2023',
                description: 'Viewed Withholding Tax ('.$frequency.')',
            );
        }

        return view('payroll.government-tables.index', $viewData);
    }

    public function store(Request $request, string $tab): RedirectResponse
    {
        $tab = GovernmentTables::resolveTab($tab);

        if (! GovernmentTables::allowsCreate($tab)) {
            abort(403);
        }

        GovernmentTables::authorize($request->user(), 'add');

        $config = GovernmentTables::config($tab);
        $validated = $request->validate(GovernmentTables::validationRules($tab));
        $payload = GovernmentTables::validatedPayload($tab, $validated);

        $record = $config['model']::query()->create($payload);

        SysLogService::record(
            action: 'create',
            table: $config['log_table'],
            recordId: $record->getKey(),
            newValues: $record->toArray(),
            description: 'Created '.$config['name'].' record: '.GovernmentTables::recordLabel($record, $tab),
        );

        return redirect()
            ->route(GovernmentTables::routeName('tab'), ['tab' => $tab])
            ->with('success', $config['name'].' record created successfully.');
    }

    public function update(Request $request, string $tab, int $record): RedirectResponse
    {
        $tab = GovernmentTables::resolveTab($tab);
        GovernmentTables::authorize($request->user(), 'update');

        $config = GovernmentTables::config($tab);
        $model = GovernmentTables::findOrFail($tab, $record);
        $oldValues = $model->toArray();

        $validated = $request->validate(GovernmentTables::validationRules($tab, $model));
        $payload = GovernmentTables::validatedPayload($tab, $validated);

        $model->update($payload);

        SysLogService::record(
            action: 'update',
            table: $config['log_table'],
            recordId: $model->getKey(),
            oldValues: $oldValues,
            newValues: $model->fresh()->toArray(),
            description: 'Updated '.$config['name'].' record: '.GovernmentTables::recordLabel($model, $tab),
        );

        return redirect()
            ->route(GovernmentTables::routeName('tab'), ['tab' => $tab])
            ->with('success', $config['name'].' record updated successfully.');
    }

    public function destroy(Request $request, string $tab, int $record): RedirectResponse
    {
        $tab = GovernmentTables::resolveTab($tab);

        if (! GovernmentTables::allowsDelete($tab)) {
            abort(403);
        }

        GovernmentTables::authorize($request->user(), 'delete');

        $config = GovernmentTables::config($tab);
        $model = GovernmentTables::findOrFail($tab, $record);
        $label = GovernmentTables::recordLabel($model, $tab);
        $oldValues = $model->toArray();

        $model->delete();

        SysLogService::record(
            action: 'delete',
            table: $config['log_table'],
            recordId: $record,
            oldValues: $oldValues,
            description: 'Deleted '.$config['name'].': '.$label,
        );

        return redirect()
            ->route(GovernmentTables::routeName('tab'), ['tab' => $tab])
            ->with('success', $config['name'].' deleted successfully.');
    }

    public function updateWtax2023Grid(Request $request, string $frequency): RedirectResponse
    {
        GovernmentTables::authorize($request->user(), 'update');

        $frequency = GovernmentTables::resolveWtax2023Frequency($frequency);

        if ($frequency === 'annual') {
            abort(404);
        }

        $typeId = GovernmentTables::WTAX2023_FREQUENCIES[$frequency]['type_id'];
        $validated = $request->validate([
            'columns' => ['required', 'array'],
            'columns.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'columns.*.tax_plus' => ['nullable', 'numeric', 'min:0'],
            'columns.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        GovernmentTables::syncWtax2023Grid($typeId, $validated['columns']);

        SysLogService::record(
            action: 'update',
            table: 'tbl_govt_table_wtax_2023',
            description: 'Updated Withholding Tax grid ('.$frequency.')',
        );

        return redirect()
            ->route(GovernmentTables::routeName('tab'), [
                'tab' => 'withholding-tax-2023',
                'frequency' => $frequency,
            ])
            ->with('success', 'Withholding Tax table saved successfully.');
    }

    public function storeWtaxAnnual(Request $request): RedirectResponse
    {
        GovernmentTables::authorize($request->user(), 'add');

        $validated = $request->validate(GovernmentTables::annualValidationRules());
        $record = GovtTableWtaxAnnual2023::query()->create($validated);

        SysLogService::record(
            action: 'create',
            table: 'tbl_govt_table_wtax_annual_2023',
            recordId: $record->govt_table_wtax_annual_2023_id,
            newValues: $record->toArray(),
            description: 'Created Withholding Tax annual range',
        );

        return redirect()
            ->route(GovernmentTables::routeName('tab'), [
                'tab' => 'withholding-tax-2023',
                'frequency' => 'annual',
            ])
            ->with('success', 'Annual tax range created successfully.');
    }

    public function updateWtaxAnnual(Request $request, int $record): RedirectResponse
    {
        GovernmentTables::authorize($request->user(), 'update');

        $model = GovtTableWtaxAnnual2023::query()->findOrFail($record);
        $oldValues = $model->toArray();

        $validated = $request->validate(GovernmentTables::annualValidationRules($model));
        $model->update($validated);

        SysLogService::record(
            action: 'update',
            table: 'tbl_govt_table_wtax_annual_2023',
            recordId: $model->govt_table_wtax_annual_2023_id,
            oldValues: $oldValues,
            newValues: $model->fresh()->toArray(),
            description: 'Updated Withholding Tax annual range',
        );

        return redirect()
            ->route(GovernmentTables::routeName('tab'), [
                'tab' => 'withholding-tax-2023',
                'frequency' => 'annual',
            ])
            ->with('success', 'Annual tax range updated successfully.');
    }

    public function destroyWtaxAnnual(Request $request, int $record): RedirectResponse
    {
        GovernmentTables::authorize($request->user(), 'delete');

        $model = GovtTableWtaxAnnual2023::query()->findOrFail($record);
        $oldValues = $model->toArray();
        $model->delete();

        SysLogService::record(
            action: 'delete',
            table: 'tbl_govt_table_wtax_annual_2023',
            recordId: $record,
            oldValues: $oldValues,
            description: 'Deleted Withholding Tax annual range',
        );

        return redirect()
            ->route(GovernmentTables::routeName('tab'), [
                'tab' => 'withholding-tax-2023',
                'frequency' => 'annual',
            ])
            ->with('success', 'Annual tax range deleted successfully.');
    }
}
