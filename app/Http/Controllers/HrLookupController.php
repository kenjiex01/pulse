<?php

namespace App\Http\Controllers;

use App\Models\EmployeeCategory;
use App\Models\LuIcctOffenseCategory;
use App\Models\LuIcctOffenseFrequency;
use App\Services\HrLookupUploadService;
use App\Services\SysLogService;
use App\Support\HrLookup;
use App\Support\LiveTable;
use Database\Seeders\CompanyDocumentIcctOffensesSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'openUpload' => session('open_upload') === $lookup
                || old('form_context') === "upload-$lookup",
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

    public function downloadTemplate(HrLookupUploadService $uploads): StreamedResponse
    {
        $lookup = HrLookup::fromRoute();
        abort_unless(HrLookup::supportsUpload($lookup), 404);
        HrLookup::authorize(request()->user(), $lookup, 'add');

        return $uploads->download($lookup);
    }

    public function upload(Request $request, HrLookupUploadService $uploads): RedirectResponse
    {
        $lookup = HrLookup::fromRoute();
        abort_unless(HrLookup::supportsUpload($lookup), 404);
        HrLookup::authorize($request->user(), $lookup, 'add');

        $config = HrLookup::config($lookup);

        $request->validate([
            'upload_file' => ['required', 'file', 'max:'.config('uploads.max_file_kb', 15360)],
            'form_context' => ['nullable', 'string'],
        ]);

        $extension = strtolower((string) $request->file('upload_file')?->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            return $this->redirectAfterChange($lookup)
                ->with('error', 'Upload a CSV or Excel template.')
                ->with('open_upload', $lookup);
        }

        $result = $uploads->import($lookup, $request->file('upload_file'));
        $saved = $result['created'] + $result['updated'];

        if ($saved === 0) {
            return $this->redirectAfterChange($lookup)
                ->with('error', $result['errors'][0] ?? 'Nothing was uploaded.')
                ->with('open_upload', $lookup)
                ->with('upload_errors', $result['errors']);
        }

        $redirect = $this->redirectAfterChange($lookup)
            ->with('success', $this->uploadSuccessMessage($config['name'], $result));

        if ($result['errors'] !== []) {
            $redirect->with('error', implode(' ', $result['errors']))
                ->with('upload_errors', $result['errors']);
        }

        return $redirect;
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

        if ($model instanceof EmployeeCategory && $model->is_protected && array_key_exists('is_active', $payload) && ! $payload['is_active']) {
            return $this->redirectAfterChange($lookup)
                ->with('error', 'Faculty and Staff stay active because Hybrid employment uses them.');
        }

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

        if ($model instanceof EmployeeCategory && $model->is_protected && $model->is_active) {
            return $this->redirectAfterChange($lookup)
                ->with('error', 'Faculty and Staff stay active because Hybrid employment uses them.');
        }

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

        if ($model instanceof EmployeeCategory && $model->is_protected) {
            return $this->redirectAfterChange($lookup)
                ->with('error', 'Faculty and Staff cannot be deleted. Hybrid employment uses those categories.');
        }

        if ($model instanceof EmployeeCategory && $model->isInUse()) {
            return $this->redirectAfterChange($lookup)
                ->with('error', 'Cannot delete this category while an employee employment record still uses it.');
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

    /**
     * @param  array{created: int, updated: int, labels: list<string>}  $result
     */
    private function uploadSuccessMessage(string $name, array $result): string
    {
        $saved = $result['created'] + $result['updated'];
        $label = $saved === 1 && isset($result['labels'][0])
            ? $result['labels'][0]
            : $saved.' records';

        return 'Uploaded '.$name.': '.$label.'.';
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
