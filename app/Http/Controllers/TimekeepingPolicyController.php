<?php

namespace App\Http\Controllers;

use App\Services\SysLogService;
use App\Support\LiveTable;
use App\Support\TimekeepingPolicy as TimekeepingPolicySupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimekeepingPolicyController extends Controller
{
    public function module(Request $request, string $tab): View
    {
        $tab = TimekeepingPolicySupport::resolveModuleTab($tab);
        TimekeepingPolicySupport::authorize($request->user(), 'view');

        if ($tab === 'shift-codes') {
            return app(ShiftCodeController::class)->index($request);
        }

        if ($tab === 'time-capturing-settings') {
            return app(TimeCapturingSettingsController::class)->index($request);
        }

        if ($tab === 'holiday-settings') {
            return app(HolidaySettingsController::class)->index($request);
        }

        if ($tab !== 'policy') {
            $config = TimekeepingPolicySupport::moduleTabConfig($tab);

            if (! $request->ajax()) {
                SysLogService::record(
                    action: 'read',
                    table: 'tbl_timekeeping_policies',
                    description: 'Viewed Timekeeping Policy — '.$config['label'],
                );
            }

            return view('timekeeping.policy.index', [
                'moduleTab' => $tab,
                'tabs' => TimekeepingPolicySupport::moduleTabs(),
                'moduleConfig' => $config,
            ]);
        }

        $search = $request->string('search')->trim()->toString();

        $policies = TimekeepingPolicySupport::policyListQuery()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('policy_code', 'like', '%'.$search.'%')
                        ->orWhere('policy_name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->paginate(LiveTable::perPage($request, 10))
            ->withQueryString();

        if (! $request->ajax()) {
            SysLogService::record(
                action: 'read',
                table: 'tbl_timekeeping_policies',
                description: 'Viewed Timekeeping Policy list ('.$policies->total().' records)',
            );
        }

        $viewData = [
            'moduleTab' => $tab,
            'tabs' => TimekeepingPolicySupport::moduleTabs(),
            'policies' => $policies,
            'search' => $search,
            'openEditId' => old('edit_record_id', $request->input('edit')),
            'openCreate' => ($request->session()->get('errors')?->any() && old('form_context') === 'create-policy') || $request->boolean('create'),
        ];

        if ($request->ajax()) {
            return view('timekeeping.policy._policy-results', $viewData);
        }

        return view('timekeeping.policy.index', $viewData);
    }

    public function store(Request $request): RedirectResponse
    {
        TimekeepingPolicySupport::authorize($request->user(), 'add');

        $validated = TimekeepingPolicySupport::validatePolicyHeader($request->all());
        $payload = TimekeepingPolicySupport::policyHeaderPayload($validated);
        $policy = TimekeepingPolicySupport::createPolicyWithDefaults($payload);

        SysLogService::record(
            action: 'create',
            table: 'tbl_timekeeping_policies',
            recordId: $policy->timekeeping_policy_id,
            newValues: $policy->fresh()->toArray(),
            description: 'Created Timekeeping Policy: '.TimekeepingPolicySupport::policyLabel($policy),
        );

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('tab'), [
                'policy' => $policy->timekeeping_policy_id,
                'tab' => TimekeepingPolicySupport::defaultSettingsTab(),
            ])
            ->with('success', 'Timekeeping policy created. Configure the settings below.');
    }

    public function updateHeader(Request $request, int $policy): RedirectResponse
    {
        TimekeepingPolicySupport::authorize($request->user(), 'update');

        $model = TimekeepingPolicySupport::findPolicyOrFail($policy);
        $oldValues = $model->toArray();

        $validated = TimekeepingPolicySupport::validatePolicyHeader($request->all(), $model);
        $payload = TimekeepingPolicySupport::policyHeaderPayload($validated);
        $model->update($payload);

        SysLogService::record(
            action: 'update',
            table: 'tbl_timekeeping_policies',
            recordId: $model->timekeeping_policy_id,
            oldValues: $oldValues,
            newValues: $model->fresh()->toArray(),
            description: 'Updated Timekeeping Policy: '.TimekeepingPolicySupport::policyLabel($model),
        );

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('index'))
            ->with('success', 'Timekeeping policy updated successfully.');
    }

    public function settings(Request $request, int $policy, ?string $tab = null): View
    {
        $tab = TimekeepingPolicySupport::resolveSettingsTab($tab);
        TimekeepingPolicySupport::authorize($request->user(), 'view');

        $policyModel = TimekeepingPolicySupport::findPolicyOrFail($policy);
        $dayCodes = TimekeepingPolicySupport::dayCodes($policyModel);
        $selectOptions = TimekeepingPolicySupport::selectOptions();
        $equivalents = TimekeepingPolicySupport::equivalentsForTab($tab);

        $equivalentRecords = [];
        foreach (array_keys($equivalents) as $type) {
            $equivalentRecords[$type] = TimekeepingPolicySupport::modelQuery($type, $policyModel)->get();
        }

        SysLogService::record(
            action: 'read',
            table: 'tbl_timekeeping_policies',
            recordId: $policyModel->timekeeping_policy_id,
            description: 'Viewed Timekeeping Policy '.TimekeepingPolicySupport::policyLabel($policyModel).' ('.TimekeepingPolicySupport::settingsTabs()[$tab].')',
        );

        return view('timekeeping.policy.index', [
            'tab' => $tab,
            'tabs' => TimekeepingPolicySupport::tabs(),
            'policy' => $policyModel,
            'dayCodes' => $dayCodes,
            'selectOptions' => $selectOptions,
            'equivalents' => $equivalents,
            'equivalentRecords' => $equivalentRecords,
            'openEditEquivalent' => [
                'type' => $request->input('edit_equivalent'),
                'id' => $request->input('edit_record_id'),
            ],
            'openCreateEquivalent' => $request->boolean('create') ? $request->input('equivalent') : null,
        ]);
    }

    public function updateSettings(Request $request, int $policy, string $tab): RedirectResponse
    {
        $tab = TimekeepingPolicySupport::resolveSettingsTab($tab);
        TimekeepingPolicySupport::authorize($request->user(), 'update');

        $policyModel = TimekeepingPolicySupport::findPolicyOrFail($policy);

        $validated = TimekeepingPolicySupport::validateSettings($tab, $request->all());
        $payload = TimekeepingPolicySupport::settingsPayload($tab, $validated);

        $oldValues = $policyModel->toArray();
        $policyModel->update($payload);

        SysLogService::record(
            action: 'update',
            table: 'tbl_timekeeping_policies',
            recordId: $policyModel->timekeeping_policy_id,
            oldValues: $oldValues,
            newValues: $policyModel->fresh()->toArray(),
            description: 'Updated Timekeeping Policy '.TimekeepingPolicySupport::policyLabel($policyModel).' ('.TimekeepingPolicySupport::settingsTabs()[$tab].')',
        );

        return redirect()
            ->route(TimekeepingPolicySupport::routeName('tab'), [
                'policy' => $policyModel->timekeeping_policy_id,
                'tab' => $tab,
            ])
            ->with('success', TimekeepingPolicySupport::settingsTabs()[$tab].' saved successfully.');
    }

    public function storeEquivalent(Request $request, int $policy, string $type): RedirectResponse
    {
        TimekeepingPolicySupport::authorize($request->user(), 'add');

        $policyModel = TimekeepingPolicySupport::findPolicyOrFail($policy);
        $config = TimekeepingPolicySupport::equivalentConfig($type);
        $validated = TimekeepingPolicySupport::validateEquivalent($type, $request->all(), $policyModel);
        $payload = TimekeepingPolicySupport::equivalentPayload($type, $validated, $policyModel);

        $record = $config['model']::query()->create($payload);

        SysLogService::record(
            action: 'create',
            table: $config['log_table'],
            recordId: $record->getKey(),
            newValues: $record->toArray(),
            description: 'Added '.$config['name'].' for '.TimekeepingPolicySupport::policyLabel($policyModel).': '.TimekeepingPolicySupport::equivalentLabel($record, $type),
        );

        return $this->redirectToTab($policyModel, $config['tab'], $type, $validated['leave_type_id'] ?? null)
            ->with('success', $config['name'].' created successfully.');
    }

    public function updateEquivalent(Request $request, int $policy, string $type, int $record): RedirectResponse
    {
        TimekeepingPolicySupport::authorize($request->user(), 'update');

        $policyModel = TimekeepingPolicySupport::findPolicyOrFail($policy);
        $config = TimekeepingPolicySupport::equivalentConfig($type);
        $model = TimekeepingPolicySupport::findEquivalentOrFail($type, $record, $policyModel);
        $oldValues = $model->toArray();

        $validated = TimekeepingPolicySupport::validateEquivalent($type, $request->all(), $policyModel, $model);
        $payload = TimekeepingPolicySupport::equivalentPayload($type, $validated, $policyModel);

        $model->update($payload);

        SysLogService::record(
            action: 'update',
            table: $config['log_table'],
            recordId: $model->getKey(),
            oldValues: $oldValues,
            newValues: $model->fresh()->toArray(),
            description: 'Updated '.$config['name'].' for '.TimekeepingPolicySupport::policyLabel($policyModel).': '.TimekeepingPolicySupport::equivalentLabel($model, $type),
        );

        return $this->redirectToTab($policyModel, $config['tab'], $type, $validated['leave_type_id'] ?? null)
            ->with('success', $config['name'].' updated successfully.');
    }

    public function destroyEquivalent(Request $request, int $policy, string $type, int $record): RedirectResponse
    {
        TimekeepingPolicySupport::authorize($request->user(), 'delete');

        $policyModel = TimekeepingPolicySupport::findPolicyOrFail($policy);
        $config = TimekeepingPolicySupport::equivalentConfig($type);
        $model = TimekeepingPolicySupport::findEquivalentOrFail($type, $record, $policyModel);
        $label = TimekeepingPolicySupport::equivalentLabel($model, $type);
        $oldValues = $model->toArray();
        $leaveTypeId = $model->leave_type_id ?? null;

        $model->delete();

        SysLogService::record(
            action: 'delete',
            table: $config['log_table'],
            recordId: $record,
            oldValues: $oldValues,
            description: 'Deleted '.$config['name'].' for '.TimekeepingPolicySupport::policyLabel($policyModel).': '.$label,
        );

        return $this->redirectToTab($policyModel, $config['tab'], $type, $leaveTypeId)
            ->with('success', $config['name'].' deleted successfully.');
    }

    private function redirectToTab($policyModel, string $tab, string $type, ?int $leaveTypeId = null): RedirectResponse
    {
        $params = [
            'policy' => $policyModel->timekeeping_policy_id,
            'tab' => $tab,
        ];

        return redirect()->route(TimekeepingPolicySupport::routeName('tab'), $params);
    }
}
