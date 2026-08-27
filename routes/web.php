<?php

use App\Http\Controllers\DesktopInstallerUpdateController;
use App\Http\Controllers\DesktopUpdaterController;
use App\Http\Controllers\DocumentPreviewEngineController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeCredentialController;
use App\Http\Controllers\EmployeeLoanController;
use App\Http\Controllers\EmployeeLookupController;
use App\Http\Controllers\EmployeeSkolarisSyncController;
use App\Http\Controllers\EmployeeUploadController;
use App\Http\Controllers\HrLookupController;
use App\Http\Controllers\BirFormSettingsController;
use App\Http\Controllers\GovernmentTablesController;
use App\Http\Controllers\PayrollCalendarController;
use App\Http\Controllers\PayrollMaintenanceController;
use App\Http\Controllers\PayrollReportsController;
use App\Http\Controllers\PayrollTransactionController;
use App\Http\Controllers\RateDefinitionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\HolidaySettingsController;
use App\Http\Controllers\ShiftCodeController;
use App\Http\Controllers\TimeCapturingSettingsController;
use App\Http\Controllers\TimekeepingEmployeeLoadController;
use App\Http\Controllers\TimekeepingEmployeeProfileController;
use App\Http\Controllers\TimekeepingPolicyController;
use App\Http\Controllers\TimekeepingTemplateController;
use App\Http\Controllers\TimeLogsController;
use App\Http\Controllers\UserController;
use App\Support\HrLookup;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('desktop/update/download', [DesktopInstallerUpdateController::class, 'download'])
    ->name('desktop.update.download');
Route::get('desktop/updater/status', [DesktopUpdaterController::class, 'status'])
    ->name('desktop.updater.status');
Route::post('desktop/updater/check', [DesktopUpdaterController::class, 'check'])
    ->name('desktop.updater.check');
Route::post('desktop/updater/install', [DesktopUpdaterController::class, 'install'])
    ->name('desktop.updater.install');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('document-preview/engine/status', [DocumentPreviewEngineController::class, 'status'])
        ->name('document-preview.engine.status');
    Route::post('document-preview/engine/install', [DocumentPreviewEngineController::class, 'install'])
        ->name('document-preview.engine.install');

    Route::middleware('role:admin')->group(function () {
        Route::get('database', [DatabaseController::class, 'index'])->name('database.index');
        Route::post('database/cloud-backup/reset-marker', [DatabaseController::class, 'resetCloudBackupMarker'])
            ->name('database.cloud-backup.reset-marker');
        Route::post('database/upload-sql', [DatabaseController::class, 'uploadSql'])
            ->name('database.upload-sql');
        Route::get('database/download', DatabaseBackupController::class)->name('database.download');
        Route::resource('users', UserController::class);
        Route::resource('roles', RoleController::class);
    });

    Route::middleware('module:employees.index')->group(function () {
        Route::get('employees/lookups/provinces', [EmployeeLookupController::class, 'provinces'])->name('employees.lookups.provinces');
        Route::get('employees/lookups/cities', [EmployeeLookupController::class, 'cities'])->name('employees.lookups.cities');
        Route::post('employees/create/cancel', [EmployeeController::class, 'wizardCancel'])->name('employees.wizard.cancel');
        Route::post('employees/create/campus', [EmployeeController::class, 'wizardCampus'])->name('employees.wizard.campus');
        Route::post('employees/create/details', [EmployeeController::class, 'wizardDetails'])->name('employees.wizard.details');
        Route::get('employees/upload/template', [EmployeeUploadController::class, 'downloadTemplate'])->name('employees.upload.template');
        Route::post('employees/upload/process', [EmployeeUploadController::class, 'processUpload'])->name('employees.upload.process');
        Route::post('employees/upload/commit', [EmployeeUploadController::class, 'commitUpload'])->name('employees.upload.commit');
        Route::post('employees/upload/discard', [EmployeeUploadController::class, 'discardStaging'])->name('employees.upload.discard');
        Route::get('employees/sync/pending', [EmployeeSkolarisSyncController::class, 'pending'])->name('employees.sync.pending');
        Route::get('employees/sync/preview', [EmployeeSkolarisSyncController::class, 'preview'])->name('employees.sync.preview');
        Route::post('employees/sync/apply', [EmployeeSkolarisSyncController::class, 'apply'])->name('employees.sync.apply');
        Route::get('employees/{employee}/history', [EmployeeController::class, 'history'])->name('employees.history');
        Route::post('employees/{employee}/credentials', [EmployeeCredentialController::class, 'store'])
            ->name('employees.credentials.store');
        Route::get('employees/{employee}/credentials/{credential}/preview', [EmployeeCredentialController::class, 'preview'])
            ->name('employees.credentials.preview');
        Route::get('employees/{employee}/credentials/{credential}/content', [EmployeeCredentialController::class, 'content'])
            ->name('employees.credentials.content');
        Route::get('employees/{employee}/credentials/{credential}/download', [EmployeeCredentialController::class, 'download'])
            ->name('employees.credentials.download');
        Route::delete('employees/{employee}/credentials/{credential}', [EmployeeCredentialController::class, 'destroy'])
            ->name('employees.credentials.destroy');
        Route::post('employees/{employee}/loans', [EmployeeLoanController::class, 'store'])
            ->name('employees.loans.store');
        Route::put('employees/{employee}/loans/{loan}', [EmployeeLoanController::class, 'update'])
            ->name('employees.loans.update');
        Route::delete('employees/{employee}/loans/{loan}', [EmployeeLoanController::class, 'destroy'])
            ->name('employees.loans.destroy');
        Route::resource('employees', EmployeeController::class)->only([
            'index', 'create', 'store', 'show', 'edit', 'update', 'destroy',
        ]);
    });

    foreach (HrLookup::keys() as $lookup) {
        Route::middleware('module:'.HrLookup::routeName($lookup))->group(function () use ($lookup) {
            Route::get("hr/$lookup", [HrLookupController::class, 'index'])->name(HrLookup::routeName($lookup));
            Route::post("hr/$lookup", [HrLookupController::class, 'store'])->name(HrLookup::routeName($lookup, 'store'));
            Route::put("hr/$lookup/{record}", [HrLookupController::class, 'update'])->name(HrLookup::routeName($lookup, 'update'));
            Route::post("hr/$lookup/{record}/toggle-status", [HrLookupController::class, 'toggleStatus'])->name(HrLookup::routeName($lookup, 'toggle-status'));
            Route::delete("hr/$lookup/{record}", [HrLookupController::class, 'destroy'])->name(HrLookup::routeName($lookup, 'destroy'));
        });
    }

    Route::middleware('module:payroll.rate-definitions.index')->group(function () {
        $tabs = 'rate-groups|nd-rate-groups|day-types';

        Route::get('payroll/rate-definitions', function () {
            return redirect()->route('payroll.rate-definitions.tab', [
                'tab' => \App\Support\RateDefinition::defaultTab(),
            ]);
        })->name('payroll.rate-definitions.index');

        Route::get('payroll/rate-definitions/income-tax-options', [RateDefinitionController::class, 'incomeTaxOptions'])
            ->name('payroll.rate-definitions.income-tax-options');

        Route::get('payroll/rate-definitions/rate-groups/create', [RateDefinitionController::class, 'createRateGroup'])
            ->name('payroll.rate-definitions.rate-groups.create');
        Route::post('payroll/rate-definitions/rate-groups', [RateDefinitionController::class, 'storeRateGroup'])
            ->name('payroll.rate-definitions.rate-groups.store');
        Route::get('payroll/rate-definitions/rate-groups/{rateGroup}/edit', [RateDefinitionController::class, 'editRateGroup'])
            ->name('payroll.rate-definitions.rate-groups.edit');
        Route::put('payroll/rate-definitions/rate-groups/{rateGroup}', [RateDefinitionController::class, 'updateRateGroup'])
            ->name('payroll.rate-definitions.rate-groups.update');

        Route::get('payroll/rate-definitions/nd-rate-groups/create', [RateDefinitionController::class, 'createNdRateGroup'])
            ->name('payroll.rate-definitions.nd-rate-groups.create');
        Route::post('payroll/rate-definitions/nd-rate-groups', [RateDefinitionController::class, 'storeNdRateGroup'])
            ->name('payroll.rate-definitions.nd-rate-groups.store');
        Route::get('payroll/rate-definitions/nd-rate-groups/{ndRateGroup}/edit', [RateDefinitionController::class, 'editNdRateGroup'])
            ->name('payroll.rate-definitions.nd-rate-groups.edit');
        Route::put('payroll/rate-definitions/nd-rate-groups/{ndRateGroup}', [RateDefinitionController::class, 'updateNdRateGroup'])
            ->name('payroll.rate-definitions.nd-rate-groups.update');

        Route::post('payroll/rate-definitions/day-types', [RateDefinitionController::class, 'storeDayType'])
            ->name('payroll.rate-definitions.day-types.store');
        Route::put('payroll/rate-definitions/day-types/{record}', [RateDefinitionController::class, 'updateDayType'])
            ->name('payroll.rate-definitions.day-types.update');

        Route::get('payroll/rate-definitions/{tab}', [RateDefinitionController::class, 'index'])
            ->where('tab', $tabs)
            ->name('payroll.rate-definitions.tab');

        Route::delete('payroll/rate-definitions/{tab}/{record}', [RateDefinitionController::class, 'destroy'])
            ->where('tab', $tabs)
            ->name('payroll.rate-definitions.destroy');
    });

    Route::middleware('module:payroll.maintenance-table.index')->group(function () {
        $tabs = 'income-types|deduction-types|loan-types|leave-types';

        Route::get('payroll/maintenance-table', function () {
            return redirect()->route('payroll.maintenance-table.tab', [
                'tab' => \App\Support\PayrollMaintenance::defaultTab(),
            ]);
        })->name('payroll.maintenance-table.index');

        Route::get('payroll/maintenance-table/{tab}', [PayrollMaintenanceController::class, 'index'])
            ->where('tab', $tabs)
            ->name('payroll.maintenance-table.tab');
        Route::post('payroll/maintenance-table/{tab}', [PayrollMaintenanceController::class, 'store'])
            ->where('tab', $tabs)
            ->name('payroll.maintenance-table.store');
        Route::put('payroll/maintenance-table/{tab}/{record}', [PayrollMaintenanceController::class, 'update'])
            ->where('tab', $tabs)
            ->name('payroll.maintenance-table.update');
        Route::post('payroll/maintenance-table/{tab}/{record}/toggle-status', [PayrollMaintenanceController::class, 'toggleStatus'])
            ->where('tab', $tabs)
            ->name('payroll.maintenance-table.toggle-status');
        Route::delete('payroll/maintenance-table/{tab}/{record}', [PayrollMaintenanceController::class, 'destroy'])
            ->where('tab', $tabs)
            ->name('payroll.maintenance-table.destroy');
    });

    Route::middleware('module:payroll.calendar.index')->group(function () {
        $payTypes = 'daily|weekly|semi-monthly|monthly';

        Route::get('payroll/calendar', function () {
            return redirect()->route('payroll.calendar.pay-type', [
                'payType' => \App\Support\PayrollCalendarModule::defaultPayTypeSlug(),
            ]);
        })->name('payroll.calendar.index');

        Route::get('payroll/calendar/priority', [PayrollCalendarController::class, 'priority'])
            ->name('payroll.calendar.priority');

        Route::post('payroll/calendar/priority/enable', [PayrollCalendarController::class, 'enablePriority'])
            ->name('payroll.calendar.enable-priority');

        Route::post('payroll/calendar/priority/{priority}/move', [PayrollCalendarController::class, 'movePriority'])
            ->whereNumber('priority')
            ->name('payroll.calendar.move-priority');

        Route::get('payroll/calendar/calendar/{payType}', [PayrollCalendarController::class, 'index'])
            ->where('payType', $payTypes)
            ->name('payroll.calendar.pay-type');

        Route::post('payroll/calendar/calendar/{payType}', [PayrollCalendarController::class, 'store'])
            ->where('payType', $payTypes)
            ->name('payroll.calendar.store');

        Route::post('payroll/calendar/calendar/{payType}/autofill', [PayrollCalendarController::class, 'autofill'])
            ->where('payType', $payTypes)
            ->name('payroll.calendar.autofill');

        Route::put('payroll/calendar/calendar/{payType}/{period}', [PayrollCalendarController::class, 'update'])
            ->where('payType', $payTypes)
            ->whereNumber('period')
            ->name('payroll.calendar.update');

        Route::delete('payroll/calendar/calendar/{payType}/bulk', [PayrollCalendarController::class, 'bulkDestroy'])
            ->where('payType', $payTypes)
            ->name('payroll.calendar.bulk-destroy');

        Route::delete('payroll/calendar/calendar/{payType}/{period}', [PayrollCalendarController::class, 'destroy'])
            ->where('payType', $payTypes)
            ->whereNumber('period')
            ->name('payroll.calendar.destroy');

        Route::post('payroll/calendar/calendar/{payType}/{period}/schedule', [PayrollCalendarController::class, 'saveSchedule'])
            ->where('payType', $payTypes)
            ->whereNumber('period')
            ->name('payroll.calendar.schedule');
    });

    Route::middleware('module:payroll.transaction.index')->group(function () {
        $tabs = 'batches|upload-transactions|unpost-batches';

        Route::get('payroll/transaction', function () {
            return redirect()->route('payroll.transaction.tab', [
                'tab' => \App\Support\PayrollTransactionModule::DEFAULT_TAB,
            ]);
        })->name('payroll.transaction.index');

        Route::get('payroll/transaction/{tab}', [PayrollTransactionController::class, 'index'])
            ->where('tab', $tabs)
            ->name('payroll.transaction.tab');

        Route::get('payroll/transaction/batches/create', [PayrollTransactionController::class, 'create'])
            ->name('payroll.transaction.create');

        Route::post('payroll/transaction/batches', [PayrollTransactionController::class, 'store'])
            ->name('payroll.transaction.store');

        Route::get('payroll/transaction/batches/{batch}', [PayrollTransactionController::class, 'show'])
            ->name('payroll.transaction.show');

        Route::get('payroll/transaction/batches/{batch}/employees/{detail}', [PayrollTransactionController::class, 'showEmployeeDetail'])
            ->whereNumber('batch')
            ->whereNumber('detail')
            ->name('payroll.transaction.employees.show');

        Route::post('payroll/transaction/batches/{batch}/employees/{detail}/incomes', [PayrollTransactionController::class, 'storeEmployeeIncome'])
            ->whereNumber('batch')
            ->whereNumber('detail')
            ->name('payroll.transaction.employees.incomes.store');

        Route::post('payroll/transaction/batches/{batch}/employees/{detail}/shift-overrides', [PayrollTransactionController::class, 'storeEmployeeShiftOverride'])
            ->whereNumber('batch')
            ->whereNumber('detail')
            ->name('payroll.transaction.employees.shift-overrides.store');

        Route::delete('payroll/transaction/batches/{batch}/employees/{detail}/shift-overrides/{override}', [PayrollTransactionController::class, 'destroyEmployeeShiftOverride'])
            ->whereNumber('batch')
            ->whereNumber('detail')
            ->whereNumber('override')
            ->name('payroll.transaction.employees.shift-overrides.destroy');

        Route::get('payroll/transaction/batches/{batch}/employees/{detail}/overtime-approvals/preview', [PayrollTransactionController::class, 'previewEmployeeOvertimeApproval'])
            ->whereNumber('batch')
            ->whereNumber('detail')
            ->name('payroll.transaction.employees.overtime-approvals.preview');

        Route::post('payroll/transaction/batches/{batch}/employees/{detail}/overtime-approvals', [PayrollTransactionController::class, 'storeEmployeeOvertimeApproval'])
            ->whereNumber('batch')
            ->whereNumber('detail')
            ->name('payroll.transaction.employees.overtime-approvals.store');

        Route::delete('payroll/transaction/batches/{batch}/employees/{detail}/overtime-approvals/{approval}', [PayrollTransactionController::class, 'destroyEmployeeOvertimeApproval'])
            ->whereNumber('batch')
            ->whereNumber('detail')
            ->whereNumber('approval')
            ->name('payroll.transaction.employees.overtime-approvals.destroy');

        Route::post('payroll/transaction/batches/{batch}/employees/{detail}/deductions', [PayrollTransactionController::class, 'storeEmployeeDeduction'])
            ->whereNumber('batch')
            ->whereNumber('detail')
            ->name('payroll.transaction.employees.deductions.store');

        Route::post('payroll/transaction/batches/{batch}/employees', [PayrollTransactionController::class, 'storeEmployees'])
            ->name('payroll.transaction.employees.store');

        Route::delete('payroll/transaction/batches/{batch}/employees', [PayrollTransactionController::class, 'destroyEmployees'])
            ->name('payroll.transaction.employees.destroy');

        Route::post('payroll/transaction/batches/{batch}/process', [PayrollTransactionController::class, 'processBatch'])
            ->name('payroll.transaction.process');

        Route::post('payroll/transaction/batches/{batch}/reprocess', [PayrollTransactionController::class, 'reprocessBatch'])
            ->name('payroll.transaction.reprocess');

        Route::post('payroll/transaction/batches/{batch}/post', [PayrollTransactionController::class, 'postBatch'])
            ->name('payroll.transaction.post');

        Route::post('payroll/transaction/batches/{batch}/unpost', [PayrollTransactionController::class, 'unpostBatch'])
            ->name('payroll.transaction.unpost');

        Route::get('payroll/transaction/upload/{uploadType}/template', [PayrollTransactionController::class, 'downloadUploadTemplate'])
            ->name('payroll.transaction.upload.template');

        Route::post('payroll/transaction/upload/process', [PayrollTransactionController::class, 'processUpload'])
            ->name('payroll.transaction.upload.process');

        Route::post('payroll/transaction/upload/commit', [PayrollTransactionController::class, 'commitUpload'])
            ->name('payroll.transaction.upload.commit');

        Route::post('payroll/transaction/upload/discard', [PayrollTransactionController::class, 'discardUploadStaging'])
            ->name('payroll.transaction.upload.discard');

        Route::delete('payroll/transaction/upload/purge', [PayrollTransactionController::class, 'destroyUploadBatches'])
            ->name('payroll.transaction.upload.destroy');
    });

    Route::middleware('module:payroll.bir-forms.index')->group(function () {
        Route::get('payroll/bir-forms', [BirFormSettingsController::class, 'index'])
            ->name('payroll.bir-forms.index');
        Route::put('payroll/bir-forms', [BirFormSettingsController::class, 'update'])
            ->name('payroll.bir-forms.update');
    });

    Route::middleware('module:payroll.reports.index')->group(function () {
        Route::get('payroll/reports', [PayrollReportsController::class, 'index'])
            ->name('payroll.reports.index');

        Route::get('payroll/reports/{report}/options', [PayrollReportsController::class, 'options'])
            ->whereNumber('report')
            ->name('payroll.reports.options');

        Route::get('payroll/reports/batch-employees', [PayrollReportsController::class, 'batchEmployees'])
            ->name('payroll.reports.batch-employees');

        Route::get('payroll/reports/year-employees', [PayrollReportsController::class, 'yearEmployees'])
            ->name('payroll.reports.year-employees');

        Route::post('payroll/reports/generate', [PayrollReportsController::class, 'generate'])
            ->name('payroll.reports.generate');
    });

    Route::middleware('module:payroll.government-tables.index')->group(function () {
        $tabs = 'pag-ibig|philhealth|philhealth-minimum|sss|withholding-tax-2023';
        $frequencies = 'daily|weekly|semi-monthly|monthly';

        Route::get('payroll/government-tables', function () {
            return redirect()->route('payroll.government-tables.tab', [
                'tab' => \App\Support\GovernmentTables::defaultTab(),
            ]);
        })->name('payroll.government-tables.index');

        Route::put('payroll/government-tables/withholding-tax-2023/{frequency}', [GovernmentTablesController::class, 'updateWtax2023Grid'])
            ->where('frequency', $frequencies)
            ->name('payroll.government-tables.wtax2023.update');

        Route::post('payroll/government-tables/withholding-tax-2023/annual', [GovernmentTablesController::class, 'storeWtaxAnnual'])
            ->name('payroll.government-tables.wtax2023-annual.store');
        Route::put('payroll/government-tables/withholding-tax-2023/annual/{record}', [GovernmentTablesController::class, 'updateWtaxAnnual'])
            ->name('payroll.government-tables.wtax2023-annual.update');
        Route::delete('payroll/government-tables/withholding-tax-2023/annual/{record}', [GovernmentTablesController::class, 'destroyWtaxAnnual'])
            ->name('payroll.government-tables.wtax2023-annual.destroy');

        Route::get('payroll/government-tables/{tab}', [GovernmentTablesController::class, 'index'])
            ->where('tab', $tabs)
            ->name('payroll.government-tables.tab');
        Route::post('payroll/government-tables/{tab}', [GovernmentTablesController::class, 'store'])
            ->where('tab', implode('|', \App\Support\GovernmentTables::storeTabs()))
            ->name('payroll.government-tables.store');
        Route::put('payroll/government-tables/{tab}/{record}', [GovernmentTablesController::class, 'update'])
            ->where('tab', implode('|', \App\Support\GovernmentTables::crudTabs()))
            ->name('payroll.government-tables.update');
        Route::delete('payroll/government-tables/{tab}/{record}', [GovernmentTablesController::class, 'destroy'])
            ->where('tab', implode('|', \App\Support\GovernmentTables::destroyTabs()))
            ->name('payroll.government-tables.destroy');
    });

    Route::middleware('module:timekeeping.policy.index')->group(function () {
        Route::get('timekeeping/policy', function () {
            return redirect()->route('timekeeping.policy.module', ['tab' => 'policy']);
        })->name('timekeeping.policy.index');

        Route::get('timekeeping/policy/templates', function () {
            return redirect()->route('timekeeping.policy.module', ['tab' => 'policy']);
        });

        Route::get('timekeeping/policy/{tab}', [TimekeepingPolicyController::class, 'module'])
            ->where('tab', implode('|', array_keys(\App\Support\TimekeepingPolicy::moduleTabs())))
            ->name('timekeeping.policy.module');

        Route::get('timekeeping/policy/settings/{tab}', function (string $tab) {
            if (! array_key_exists($tab, \App\Support\TimekeepingPolicy::settingsTabs())) {
                abort(404);
            }

            $policy = \App\Models\TimekeepingPolicy::query()
                ->where('is_active', true)
                ->orderBy('policy_name')
                ->first();

            if (! $policy) {
                return redirect()->route('timekeeping.policy.index');
            }

            return redirect()->route('timekeeping.policy.tab', [
                'policy' => $policy->timekeeping_policy_id,
                'tab' => $tab,
            ]);
        })
            ->where('tab', implode('|', array_keys(\App\Support\TimekeepingPolicy::settingsTabs())))
            ->name('timekeeping.policy.legacy-tab');

        Route::post('timekeeping/shift-codes', [ShiftCodeController::class, 'store'])
            ->name('timekeeping.shift-codes.store');
        Route::put('timekeeping/shift-codes/{shiftCode}', [ShiftCodeController::class, 'update'])
            ->whereNumber('shiftCode')
            ->name('timekeeping.shift-codes.update');
        Route::delete('timekeeping/shift-codes/{shiftCode}', [ShiftCodeController::class, 'destroy'])
            ->whereNumber('shiftCode')
            ->name('timekeeping.shift-codes.destroy');

        Route::post('timekeeping/time-capture-formats', [TimeCapturingSettingsController::class, 'storeFormat'])
            ->name('timekeeping.time-capture-formats.store');
        Route::put('timekeeping/time-capture-formats/{timeCaptureFormat}', [TimeCapturingSettingsController::class, 'updateFormat'])
            ->whereNumber('timeCaptureFormat')
            ->name('timekeeping.time-capture-formats.update');
        Route::delete('timekeeping/time-capture-formats/{timeCaptureFormat}', [TimeCapturingSettingsController::class, 'destroyFormat'])
            ->whereNumber('timeCaptureFormat')
            ->name('timekeeping.time-capture-formats.destroy');

        Route::post('timekeeping/templates', [TimekeepingTemplateController::class, 'store'])
            ->name('timekeeping.templates.store');
        Route::put('timekeeping/templates/{timekeepingTemplate}', [TimekeepingTemplateController::class, 'update'])
            ->whereNumber('timekeepingTemplate')
            ->name('timekeeping.templates.update');
        Route::post('timekeeping/templates/{timekeepingTemplate}/toggle-status', [TimekeepingTemplateController::class, 'toggleStatus'])
            ->whereNumber('timekeepingTemplate')
            ->name('timekeeping.templates.toggle-status');
        Route::delete('timekeeping/templates/{timekeepingTemplate}', [TimekeepingTemplateController::class, 'destroy'])
            ->whereNumber('timekeepingTemplate')
            ->name('timekeeping.templates.destroy');

        Route::post('timekeeping/holidays', [HolidaySettingsController::class, 'storeHoliday'])
            ->name('timekeeping.holiday-settings.store-holiday');
        Route::put('timekeeping/holidays/{holiday}', [HolidaySettingsController::class, 'updateHoliday'])
            ->whereNumber('holiday')
            ->name('timekeeping.holiday-settings.update-holiday');
        Route::delete('timekeeping/holidays/{holiday}', [HolidaySettingsController::class, 'destroyHoliday'])
            ->whereNumber('holiday')
            ->name('timekeeping.holiday-settings.destroy-holiday');

        Route::post('timekeeping/holiday-groups', [HolidaySettingsController::class, 'storeGroup'])
            ->name('timekeeping.holiday-settings.store-group');
        Route::put('timekeeping/holiday-groups/{group}', [HolidaySettingsController::class, 'updateGroup'])
            ->whereNumber('group')
            ->name('timekeeping.holiday-settings.update-group');
        Route::delete('timekeeping/holiday-groups/{group}', [HolidaySettingsController::class, 'destroyGroup'])
            ->whereNumber('group')
            ->name('timekeeping.holiday-settings.destroy-group');

        Route::post('timekeeping/holiday-years', [HolidaySettingsController::class, 'storeYear'])
            ->name('timekeeping.holiday-settings.store-year');
        Route::put('timekeeping/holiday-years/{year}', [HolidaySettingsController::class, 'updateYear'])
            ->whereNumber('year')
            ->name('timekeeping.holiday-settings.update-year');
        Route::delete('timekeeping/holiday-years/{year}', [HolidaySettingsController::class, 'destroyYear'])
            ->whereNumber('year')
            ->name('timekeeping.holiday-settings.destroy-year');

        Route::post('timekeeping/holiday-years/{year}/entries', [HolidaySettingsController::class, 'storeYearEntry'])
            ->whereNumber('year')
            ->name('timekeeping.holiday-settings.store-year-entry');
        Route::put('timekeeping/holiday-years/{year}/entries/{entry}', [HolidaySettingsController::class, 'updateYearEntry'])
            ->whereNumber('year')
            ->whereNumber('entry')
            ->name('timekeeping.holiday-settings.update-year-entry');
        Route::delete('timekeeping/holiday-years/{year}/entries/{entry}', [HolidaySettingsController::class, 'destroyYearEntry'])
            ->whereNumber('year')
            ->whereNumber('entry')
            ->name('timekeeping.holiday-settings.destroy-year-entry');

        Route::post('timekeeping/policy', [TimekeepingPolicyController::class, 'store'])
            ->name('timekeeping.policy.store');

        Route::put('timekeeping/policy/{policy}', [TimekeepingPolicyController::class, 'updateHeader'])
            ->whereNumber('policy')
            ->name('timekeeping.policy.update-header');

        Route::get('timekeeping/policy/{policy}/{tab}', [TimekeepingPolicyController::class, 'settings'])
            ->whereNumber('policy')
            ->where('tab', implode('|', array_keys(\App\Support\TimekeepingPolicy::settingsTabs())))
            ->name('timekeeping.policy.tab');

        Route::put('timekeeping/policy/{policy}/{tab}', [TimekeepingPolicyController::class, 'updateSettings'])
            ->whereNumber('policy')
            ->where('tab', implode('|', array_keys(\App\Support\TimekeepingPolicy::settingsTabs())))
            ->name('timekeeping.policy.update');

        Route::post('timekeeping/policy/{policy}/equivalents/{type}', [TimekeepingPolicyController::class, 'storeEquivalent'])
            ->whereNumber('policy')
            ->where('type', implode('|', \App\Support\TimekeepingPolicy::equivalentKeys()))
            ->name('timekeeping.policy.equivalents.store');

        Route::put('timekeeping/policy/{policy}/equivalents/{type}/{record}', [TimekeepingPolicyController::class, 'updateEquivalent'])
            ->whereNumber('policy')
            ->where('type', implode('|', \App\Support\TimekeepingPolicy::equivalentKeys()))
            ->name('timekeeping.policy.equivalents.update');

        Route::delete('timekeeping/policy/{policy}/equivalents/{type}/{record}', [TimekeepingPolicyController::class, 'destroyEquivalent'])
            ->whereNumber('policy')
            ->where('type', implode('|', \App\Support\TimekeepingPolicy::equivalentKeys()))
            ->name('timekeeping.policy.equivalents.destroy');
    });

    Route::middleware('module:timekeeping.time-logs.index')->group(function () {
        Route::get('timekeeping/time-logs', function () {
            return redirect()->route('timekeeping.time-logs.tab', ['tab' => \App\Support\TimeLogs::defaultTab()]);
        })->name('timekeeping.time-logs.index');

        Route::get('timekeeping/time-logs/formats/{timeCaptureFormat}/template', [TimeLogsController::class, 'downloadTemplate'])
            ->name('timekeeping.time-logs.template');

        Route::post('timekeeping/time-logs/upload/process', [TimeLogsController::class, 'processUpload'])
            ->name('timekeeping.time-logs.process');

        Route::post('timekeeping/time-logs/upload/commit', [TimeLogsController::class, 'commit'])
            ->name('timekeeping.time-logs.commit');

        Route::post('timekeeping/time-logs/upload/discard', [TimeLogsController::class, 'discardStaging'])
            ->name('timekeeping.time-logs.discard');

        Route::post('timekeeping/time-logs/s3-pull', [TimeLogsController::class, 'pullBiometricLogsFromS3'])
            ->name('timekeeping.time-logs.s3-pull');

        Route::get('timekeeping/time-logs/s3-pull/folders', [TimeLogsController::class, 'listBiometricS3Folders'])
            ->name('timekeeping.time-logs.s3-folders');

        Route::delete('timekeeping/time-logs/{tab}/purge', [TimeLogsController::class, 'destroy'])
            ->where('tab', implode('|', array_keys(\App\Support\TimeLogs::tabs())))
            ->name('timekeeping.time-logs.destroy');

        Route::get('timekeeping/time-logs/{tab}/{transaction}', [TimeLogsController::class, 'show'])
            ->where('tab', implode('|', array_keys(\App\Support\TimeLogs::tabs())))
            ->name('timekeeping.time-logs.show');


        Route::post('timekeeping/time-logs/teaching-loads/pull/start', [TimeLogsController::class, 'startTeachingLoadPull'])
            ->name('timekeeping.time-logs.pull.start');

        Route::post('timekeeping/time-logs/teaching-loads/pull/step', [TimeLogsController::class, 'stepTeachingLoadPull'])
            ->name('timekeeping.time-logs.pull.step');

        Route::get('timekeeping/time-logs/{tab}', [TimeLogsController::class, 'index'])
            ->where('tab', implode('|', array_keys(\App\Support\TimeLogs::tabs())))
            ->name('timekeeping.time-logs.tab');
    });

    Route::middleware('module:timekeeping.employee-profile.index')->group(function () {
        Route::get('timekeeping/employee-profile', [TimekeepingEmployeeProfileController::class, 'index'])
            ->name('timekeeping.employee-profile.index');

        Route::get('timekeeping/employee-profile/{employee}', [TimekeepingEmployeeProfileController::class, 'show'])
            ->whereNumber('employee')
            ->name('timekeeping.employee-profile.show');

        Route::post('timekeeping/employee-profile/{employee}/setup', [TimekeepingEmployeeProfileController::class, 'store'])
            ->whereNumber('employee')
            ->name('timekeeping.employee-profile.store');

        Route::get('timekeeping/employee-profile/{employee}/approval-settings', [TimekeepingEmployeeProfileController::class, 'approvalSettings'])
            ->whereNumber('employee')
            ->name('timekeeping.employee-profile.approval');

        Route::get('timekeeping/employee-profile/{employee}/approval-routes', [TimekeepingEmployeeProfileController::class, 'approvalRoutes'])
            ->whereNumber('employee')
            ->name('timekeeping.employee-profile.approval-routes');

        Route::get('timekeeping/employee-profile/{employee}/attendance-view', [TimekeepingEmployeeProfileController::class, 'attendanceView'])
            ->whereNumber('employee')
            ->name('timekeeping.employee-profile.attendance');

        Route::get('timekeeping/employee-profile/{employee}/attendance-view/pdf', [TimekeepingEmployeeProfileController::class, 'downloadAttendanceViewPdf'])
            ->whereNumber('employee')
            ->name('timekeeping.employee-profile.attendance-pdf');

        Route::get('timekeeping/employee-profile/{employee}/calendar-view', [TimekeepingEmployeeProfileController::class, 'calendarView'])
            ->whereNumber('employee')
            ->name('timekeeping.employee-profile.calendar');

        Route::post('timekeeping/employee-profile/{employee}/attendance-logs', [TimekeepingEmployeeProfileController::class, 'storeAttendanceLog'])
            ->whereNumber('employee')
            ->name('timekeeping.employee-profile.attendance-store');

        Route::put('timekeeping/employee-profile/{employee}/attendance-logs/{attendanceLog}', [TimekeepingEmployeeProfileController::class, 'updateAttendanceLog'])
            ->whereNumber('employee')
            ->whereNumber('attendanceLog')
            ->name('timekeeping.employee-profile.attendance-update');

        Route::delete('timekeeping/employee-profile/{employee}/attendance-logs/{attendanceLog}', [TimekeepingEmployeeProfileController::class, 'destroyAttendanceLog'])
            ->whereNumber('employee')
            ->whereNumber('attendanceLog')
            ->name('timekeeping.employee-profile.attendance-destroy');

        Route::get('timekeeping/employee-profile/{employee}/employee-load', [TimekeepingEmployeeProfileController::class, 'employeeLoadView'])
            ->whereNumber('employee')
            ->name('timekeeping.employee-profile.employee-load');

        Route::get('timekeeping/employee-profile/upload/template', [TimekeepingEmployeeProfileController::class, 'downloadUploadTemplate'])
            ->name('timekeeping.employee-profile.upload.template');

        Route::post('timekeeping/employee-profile/upload/process', [TimekeepingEmployeeProfileController::class, 'processUpload'])
            ->name('timekeeping.employee-profile.upload.process');

        Route::post('timekeeping/employee-profile/upload/commit', [TimekeepingEmployeeProfileController::class, 'commitUpload'])
            ->name('timekeeping.employee-profile.upload.commit');

        Route::post('timekeeping/employee-profile/upload/discard', [TimekeepingEmployeeProfileController::class, 'discardUpload'])
            ->name('timekeeping.employee-profile.upload.discard');
    });

    Route::middleware('module:timekeeping.employee-load.index')->group(function () {
        Route::get('timekeeping/employee-load', [TimekeepingEmployeeLoadController::class, 'index'])
            ->name('timekeeping.employee-load.index');

        Route::get('timekeeping/employee-load/template', [TimekeepingEmployeeLoadController::class, 'downloadTemplate'])
            ->name('timekeeping.employee-load.template');

        Route::post('timekeeping/employee-load/upload/process', [TimekeepingEmployeeLoadController::class, 'processUpload'])
            ->name('timekeeping.employee-load.upload.process');

        Route::post('timekeeping/employee-load/upload/commit', [TimekeepingEmployeeLoadController::class, 'commitUpload'])
            ->name('timekeeping.employee-load.upload.commit');

        Route::post('timekeeping/employee-load/upload/discard', [TimekeepingEmployeeLoadController::class, 'discardStaging'])
            ->name('timekeeping.employee-load.upload.discard');

        Route::put('timekeeping/employee-load/entries/{entry}', [TimekeepingEmployeeLoadController::class, 'updateEntry'])
            ->name('timekeeping.employee-load.entries.update');

        Route::delete('timekeeping/employee-load/purge', [TimekeepingEmployeeLoadController::class, 'destroy'])
            ->name('timekeeping.employee-load.destroy');
    });
});
