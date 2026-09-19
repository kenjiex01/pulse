<?php

namespace App\Http\Controllers;

use App\Models\LuIcctOffenseCategory;
use App\Models\LuIcctOffenseFrequency;
use App\Services\SysLogService;
use App\Support\HrLookup;
use App\Support\LiveTable;
use Database\Seeders\CompanyDocumentIcctOffensesSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrLookupController extends Controller
{
    public function index(Request $request): View
    {
        $lookup = HrLookup::fromRoute();
        HrLookup::authorize($request->user(), $lookup, 'view');

        $config = HrLookup::config($lookup);
        $search = $request->string('search')->trim()->toString();

        $records = HrLookup::modelQuery($lookup)
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

        $config['fields'] = HrLookup::fields($lookup);

        $viewData = [
            'lookup' => $lookup,
            'config' => $config,
            'records' => $records,
            'search' => $search,
            'penaltyMatrix' => $lookup === 'offense-categories'
                ? LuIcctOffenseCategory::penaltyMatrix()
                : [],
            'categories' => $lookup === 'offense-categories'
                ? LuIcctOffenseCategory::catalogOrdered()
                : collect(),
            'frequencies' => $lookup === 'offense-categories'
                ? LuIcctOffenseFrequency::catalogOrdered()
                : collect(),
            'frequencyConfig' => HrLookup::config('offense-frequencies'),
            'selectOptions' => [
                'campuses' => HrLookup::selectOptions('campuses'),
                'regions' => HrLookup::selectOptions('regions'),
                'provinces' => HrLookup::selectOptions('provinces'),
                'offense-categories' => HrLookup::selectOptions('offense-categories'),
            ],
            'openEditId' => $request->input('edit'),
        ];

        if ($request->ajax()) {
            return view('hr-lookups._results', $viewData);
        }

        return view('hr-lookups.index', $viewData);
    }

    public function store(Request $request): RedirectResponse
    {
        $lookup = HrLookup::fromRoute();
        HrLookup::authorize($request->user(), $lookup, 'add');

        $config = HrLookup::config($lookup);
        $validated = $request->validate(HrLookup::validationRules($lookup));
        $payload = HrLookup::validatedPayload($lookup, $validated);

        $record = $config['model']::query()->create($payload);

        if ($lookup === 'offense-categories' && $record instanceof LuIcctOffenseCategory) {
            $request->validate([
                'penalties' => ['nullable', 'array'],
                'penalties.*' => ['nullable', 'string', 'max:120'],
            ]);

            $record->syncPenalties($request->input('penalties', []));
        }

        $this->runAfterChange($config);

        SysLogService::record(
            action: 'create',
            table: $config['log_table'],
            recordId: $record->getKey(),
            newValues: $record->fresh()->toArray(),
            description: 'Created '.$config['name'].' record: '.HrLookup::recordLabel($record, $lookup),
        );

        return $this->redirectAfterChange($lookup)
            ->with('success', $config['name'].' record created successfully.');
    }

    public function update(Request $request, int $record): RedirectResponse
    {
        $lookup = HrLookup::fromRoute();
        HrLookup::authorize($request->user(), $lookup, 'update');

        $config = HrLookup::config($lookup);
        $model = HrLookup::findOrFail($lookup, $record);
        $oldValues = $model->toArray();

        $validated = $request->validate(HrLookup::validationRules($lookup, $model));
        $payload = HrLookup::validatedPayload($lookup, $validated);

        $model->update($payload);

        if ($lookup === 'offense-categories' && $model instanceof LuIcctOffenseCategory) {
            $request->validate([
                'penalties' => ['nullable', 'array'],
                'penalties.*' => ['nullable', 'string', 'max:120'],
            ]);

            $model->syncPenalties($request->input('penalties', []));
        }

        $this->runAfterChange($config);

        SysLogService::record(
            action: 'update',
            table: $config['log_table'],
            recordId: $model->getKey(),
            oldValues: $oldValues,
            newValues: $model->fresh()->toArray(),
            description: 'Updated '.$config['name'].' record: '.HrLookup::recordLabel($model, $lookup),
        );

        return $this->redirectAfterChange($lookup)
            ->with('success', $config['name'].' record updated successfully.');
    }

    public function toggleStatus(Request $request, int $record): RedirectResponse
    {
        $lookup = HrLookup::fromRoute();
        HrLookup::authorize($request->user(), $lookup, 'update');

        $config = HrLookup::config($lookup);
        $model = HrLookup::findOrFail($lookup, $record);
        $oldValues = $model->toArray();

        $model->update(['is_active' => ! $model->is_active]);
        $this->runAfterChange($config);

        SysLogService::record(
            action: 'update',
            table: $config['log_table'],
            recordId: $model->getKey(),
            oldValues: $oldValues,
            newValues: $model->fresh()->toArray(),
            description: 'Toggled '.$config['name'].' status: '.HrLookup::recordLabel($model, $lookup),
        );

        $statusLabel = $model->is_active ? 'active' : 'inactive';

        return $this->redirectAfterChange($lookup)
            ->with('success', $config['name'].' marked as '.$statusLabel.'.');
    }

    public function destroy(Request $request, int $record): RedirectResponse
    {
        $lookup = HrLookup::fromRoute();
        HrLookup::authorize($request->user(), $lookup, 'delete');

        $config = HrLookup::config($lookup);
        $model = HrLookup::findOrFail($lookup, $record);

        if ($model->is_active) {
            return $this->redirectAfterChange($lookup)
                ->with('error', 'Only inactive records can be deleted. Deactivate the record first.');
        }

        if ($lookup === 'offense-categories' && $model instanceof LuIcctOffenseCategory && $model->isInUse()) {
            return $this->redirectAfterChange($lookup)
                ->with('error', 'Cannot delete this category while it is assigned to one or more offenses.');
        }

        if ($lookup === 'offense-frequencies' && $model instanceof LuIcctOffenseFrequency && $model->isInUse()) {
            return $this->redirectAfterChange($lookup)
                ->with('error', 'Cannot delete this frequency while penalty cells still use it. Clear those cells first.');
        }
        $oldValues = $model->toArray();
        $label = HrLookup::recordLabel($model, $lookup);
        $recordId = $model->getKey();

        $model->delete();
        $this->runAfterChange($config);

        SysLogService::record(
            action: 'delete',
            table: $config['log_table'],
            recordId: $recordId,
            oldValues: $oldValues,
            description: 'Deleted '.$config['name'].' record: '.$label,
        );

        return $this->redirectAfterChange($lookup)
            ->with('success', $config['name'].' record deleted successfully.');
    }

    private function redirectAfterChange(string $lookup): RedirectResponse
    {
        if ($lookup === 'offense-frequencies') {
            return redirect()->route('hr.offense-categories.index');
        }

        return redirect()->route(HrLookup::routeName($lookup));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runAfterChange(array $config): void
    {
        if (($config['after_change'] ?? null) === 'icct-offense-dropdowns') {
            (new CompanyDocumentIcctOffensesSeeder)->syncOffenseBlockDropdowns();
        }
    }
}
