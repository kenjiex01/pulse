<?php

namespace App\Http\Controllers;

use App\Models\DayType;
use App\Models\NdRateGroup;
use App\Models\RateGroup;
use App\Models\TimeType;
use App\Services\SysLogService;
use App\Support\LiveTable;
use App\Support\RateDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RateDefinitionController extends Controller
{
    public function index(Request $request, string $tab): View
    {
        $tab = RateDefinition::resolveTab($tab);
        RateDefinition::authorize($request->user(), 'view');

        $config = RateDefinition::config($tab);
        $search = $request->string('search')->trim()->toString();

        $records = RateDefinition::modelQuery($tab)
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
            'tabs' => RateDefinition::tabs(),
            'config' => $config,
            'records' => $records,
            'search' => $search,
            'selectOptions' => RateDefinition::selectOptions(),
            'openEditId' => $request->input('edit'),
        ];

        if ($request->ajax()) {
            return view('payroll.rate-definitions._results', $viewData);
        }

        return view('payroll.rate-definitions.index', $viewData);
    }

    public function storeDayType(Request $request): RedirectResponse
    {
        RateDefinition::authorize($request->user(), 'add');

        $validated = $request->validate(RateDefinition::validationRules('day-types'));
        $payload = RateDefinition::validatedDayTypePayload($validated);

        $record = DayType::query()->create($payload);

        SysLogService::record(
            action: 'create',
            table: 'tbl_day_types',
            recordId: $record->day_type_id,
            newValues: $record->toArray(),
            description: 'Created Day Type: '.$record->description,
        );

        return redirect()
            ->route(RateDefinition::routeName('tab'), ['tab' => 'day-types'])
            ->with('success', 'Day Type record created successfully.');
    }

    public function updateDayType(Request $request, int $record): RedirectResponse
    {
        RateDefinition::authorize($request->user(), 'update');

        $dayType = DayType::query()->findOrFail($record);
        $oldValues = $dayType->toArray();

        $rules = RateDefinition::validationRules('day-types', $dayType);
        if ($dayType->isInUse()) {
            unset($rules['day_type_code']);
        }

        $validated = $request->validate($rules);
        $payload = RateDefinition::validatedDayTypePayload($validated, $dayType);

        $dayType->update($payload);

        SysLogService::record(
            action: 'update',
            table: 'tbl_day_types',
            recordId: $dayType->day_type_id,
            oldValues: $oldValues,
            newValues: $dayType->fresh()->toArray(),
            description: 'Updated Day Type: '.$dayType->description,
        );

        return redirect()
            ->route(RateDefinition::routeName('tab'), ['tab' => 'day-types'])
            ->with('success', 'Day Type record updated successfully.');
    }

    public function destroy(Request $request, string $tab, int $record): RedirectResponse
    {
        RateDefinition::authorize($request->user(), 'delete');

        $config = RateDefinition::config(RateDefinition::resolveTab($tab));
        $model = RateDefinition::findOrFail($tab, $record);

        if ($tab === 'day-types' && $model instanceof DayType && $model->isInUse()) {
            return redirect()
                ->route(RateDefinition::routeName('tab'), ['tab' => $tab])
                ->with('error', 'Cannot delete a day type that is in use by rate groups.');
        }

        $label = RateDefinition::recordLabel($model, $tab);
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
            ->route(RateDefinition::routeName('tab'), ['tab' => $tab])
            ->with('success', $config['name'].' deleted successfully.');
    }

    public function createRateGroup(Request $request): View
    {
        RateDefinition::authorize($request->user(), 'add');

        return view('payroll.rate-definitions.rate-groups.form', [
            'rateGroup' => null,
            'existingRates' => [],
            ...RateDefinition::formContextData(TimeType::TIME_CLASS_REGULAR),
        ]);
    }

    public function storeRateGroup(Request $request): RedirectResponse
    {
        RateDefinition::authorize($request->user(), 'add');

        $validated = $request->validate(RateDefinition::rateGroupHeaderRules());
        $rates = $request->input('rates', []);

        $rateGroup = RateGroup::query()->create([
            'rate_group_code' => strtoupper($validated['rate_group_code']),
            'description' => $validated['description'],
            'rate_basis_id' => $validated['rate_basis_id'],
        ]);

        RateDefinition::syncRateGroupDayTypes($rateGroup, $rates, TimeType::TIME_CLASS_REGULAR);

        SysLogService::record(
            action: 'create',
            table: 'tbl_rate_groups',
            recordId: $rateGroup->rate_group_id,
            newValues: $rateGroup->fresh()->toArray(),
            description: 'Created Rate Group: '.$rateGroup->description,
        );

        return redirect()
            ->route(RateDefinition::routeName('tab'), ['tab' => 'rate-groups'])
            ->with('success', 'Rate Group created successfully.');
    }

    public function editRateGroup(Request $request, int $rateGroup): View
    {
        RateDefinition::authorize($request->user(), 'update');

        $model = RateGroup::query()
            ->with(['dayTypeRates.timeType'])
            ->findOrFail($rateGroup);

        return view('payroll.rate-definitions.rate-groups.form', [
            'rateGroup' => $model,
            'existingRates' => RateDefinition::existingRatesMap($model, TimeType::TIME_CLASS_REGULAR),
            ...RateDefinition::formContextData(TimeType::TIME_CLASS_REGULAR),
        ]);
    }

    public function updateRateGroup(Request $request, int $rateGroup): RedirectResponse
    {
        RateDefinition::authorize($request->user(), 'update');

        $model = RateGroup::query()->findOrFail($rateGroup);
        $oldValues = $model->toArray();

        $validated = $request->validate(RateDefinition::rateGroupHeaderRules($model));
        $rates = $request->input('rates', []);

        $model->update([
            'rate_group_code' => strtoupper($validated['rate_group_code']),
            'description' => $validated['description'],
            'rate_basis_id' => $validated['rate_basis_id'],
        ]);

        RateDefinition::syncRateGroupDayTypes($model, $rates, TimeType::TIME_CLASS_REGULAR);

        SysLogService::record(
            action: 'update',
            table: 'tbl_rate_groups',
            recordId: $model->rate_group_id,
            oldValues: $oldValues,
            newValues: $model->fresh()->toArray(),
            description: 'Updated Rate Group: '.$model->description,
        );

        return redirect()
            ->route(RateDefinition::routeName('tab'), ['tab' => 'rate-groups'])
            ->with('success', 'Rate Group updated successfully.');
    }

    public function createNdRateGroup(Request $request): View
    {
        RateDefinition::authorize($request->user(), 'add');

        return view('payroll.rate-definitions.nd-rate-groups.form', [
            'ndRateGroup' => null,
            'existingRates' => [],
            ...RateDefinition::formContextData(TimeType::TIME_CLASS_NIGHT_DIFF),
        ]);
    }

    public function storeNdRateGroup(Request $request): RedirectResponse
    {
        RateDefinition::authorize($request->user(), 'add');

        $validated = $request->validate(RateDefinition::ndRateGroupHeaderRules());
        $rates = $request->input('rates', []);

        $ndRateGroup = NdRateGroup::query()->create([
            'nd_rate_group_code' => strtoupper($validated['nd_rate_group_code']),
            'description' => $validated['description'],
            'rate_basis_id' => $validated['rate_basis_id'],
            'tm_start' => $validated['tm_start'],
            'tm_end' => $validated['tm_end'],
        ]);

        RateDefinition::syncNdRateGroupDayTypes($ndRateGroup, $rates, TimeType::TIME_CLASS_NIGHT_DIFF);

        SysLogService::record(
            action: 'create',
            table: 'tbl_nd_rate_groups',
            recordId: $ndRateGroup->nd_rate_group_id,
            newValues: $ndRateGroup->fresh()->toArray(),
            description: 'Created Night Diff. Rate Group: '.$ndRateGroup->description,
        );

        return redirect()
            ->route(RateDefinition::routeName('tab'), ['tab' => 'nd-rate-groups'])
            ->with('success', 'Night Diff. Rate Group created successfully.');
    }

    public function editNdRateGroup(Request $request, int $ndRateGroup): View
    {
        RateDefinition::authorize($request->user(), 'update');

        $model = NdRateGroup::query()
            ->with(['dayTypeRates.timeType'])
            ->findOrFail($ndRateGroup);

        return view('payroll.rate-definitions.nd-rate-groups.form', [
            'ndRateGroup' => $model,
            'existingRates' => RateDefinition::existingRatesMap($model, TimeType::TIME_CLASS_NIGHT_DIFF),
            ...RateDefinition::formContextData(TimeType::TIME_CLASS_NIGHT_DIFF),
        ]);
    }

    public function updateNdRateGroup(Request $request, int $ndRateGroup): RedirectResponse
    {
        RateDefinition::authorize($request->user(), 'update');

        $model = NdRateGroup::query()->findOrFail($ndRateGroup);
        $oldValues = $model->toArray();

        $validated = $request->validate(RateDefinition::ndRateGroupHeaderRules($model));
        $rates = $request->input('rates', []);

        $model->update([
            'nd_rate_group_code' => strtoupper($validated['nd_rate_group_code']),
            'description' => $validated['description'],
            'rate_basis_id' => $validated['rate_basis_id'],
            'tm_start' => $validated['tm_start'],
            'tm_end' => $validated['tm_end'],
        ]);

        RateDefinition::syncNdRateGroupDayTypes($model, $rates, TimeType::TIME_CLASS_NIGHT_DIFF);

        SysLogService::record(
            action: 'update',
            table: 'tbl_nd_rate_groups',
            recordId: $model->nd_rate_group_id,
            oldValues: $oldValues,
            newValues: $model->fresh()->toArray(),
            description: 'Updated Night Diff. Rate Group: '.$model->description,
        );

        return redirect()
            ->route(RateDefinition::routeName('tab'), ['tab' => 'nd-rate-groups'])
            ->with('success', 'Night Diff. Rate Group updated successfully.');
    }

    public function incomeTaxOptions(Request $request): JsonResponse
    {
        RateDefinition::authorize($request->user(), 'view');

        $incomeTypeId = $request->integer('income_type_id');

        return response()->json([
            'options' => RateDefinition::incomeTaxOptions($incomeTypeId ?: null),
        ]);
    }
}
