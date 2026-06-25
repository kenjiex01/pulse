<?php

use App\Models\LeaveType;
use App\Models\TimekeepingPolicy;
use App\Models\User;
use App\Support\TimekeepingPolicy as Support;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$policy = TimekeepingPolicy::query()->first();
if (! $policy) {
    echo "FAIL: No policy found\n";
    exit(1);
}

$user = User::query()->where('email', 'superadmin@icct.edu.ph')->first()
    ?? User::query()->first();

if (! $user) {
    echo "FAIL: No user found\n";
    exit(1);
}

Auth::login($user);

$leaveTypeId = LeaveType::query()->value('leave_type_id');
$teamSettingId = Support::selectOptions()['team_settings'] ? array_key_first(Support::selectOptions()['team_settings']) : null;

$payloads = [
    'tardiness-undertime' => [
        'is_allow_flexi_time' => '1',
        'max_flexi_time' => '30',
        'grace_period' => '15',
        'is_deduct_grace_period' => '1',
        'tardiness_leave_type_id' => $leaveTypeId,
        'undertime_leave_type_id' => $leaveTypeId,
    ],
    'overtime' => [
        'excess_hour_id' => '2',
        'is_ot_form_required' => '0',
        'is_consider_before_time' => '1',
        'is_consider_after_time' => '0',
        'min_minutes' => '30',
    ],
    'breaks' => [
        'break_computation' => '1',
        'is_fix_break' => '0',
        'break_deduct_tardiness' => '0',
    ],
    'leaves-absences' => [
        'hide_negative_leaves' => '0',
        'enable_notification' => '0',
        'awol_leave_type_id' => $leaveTypeId,
        'leave_processing_mode' => '1',
        'validity_of_late_file' => '30',
    ],
    'night-differential' => [
        'compute_night_diff' => '1',
        'night_diff_start' => '22:00',
        'night_diff_end' => '06:00',
        'nd_deduct_break' => '1',
    ],
    'general' => [
        'enable_attendance_approval' => '1',
        'buffer_time_in' => '4',
        'buffer_time_out' => '10',
        'non_regular_hours_computation_basis' => '2',
        'enable_employee_validation_for_rest_days' => '0',
    ],
    'team-settings' => [
        'timekeeping_policy_team_setting_id' => $teamSettingId,
    ],
    'toil-settings' => [
        'enable_toil' => '1',
        'exp_days' => '30',
        'min_toil_hours' => '1',
        'max_toil_hours' => '8',
    ],
    'logs-tagging' => [
        'enable_logs_tagging' => '1',
        'raw_logs_tag' => 'R',
        'raw_logs_desc' => 'Raw',
        'edited_logs_tag' => 'E',
        'edited_logs_desc' => 'Edited',
        'filed_logs_tag' => 'F',
        'filed_logs_desc' => 'Filed',
        'auto_logs_tag' => 'A',
        'auto_logs_desc' => 'Auto',
        'default_shift_tag' => 'D',
        'default_shift_desc' => 'Default',
        'planned_shift_tag' => 'P',
        'planned_shift_desc' => 'Planned',
        'filed_shift_tag' => 'S',
        'filed_shift_desc' => 'Filed Shift',
        'edited_shift_tag' => 'T',
        'edited_shift_desc' => 'Edited Shift',
    ],
];

echo "Policy #{$policy->timekeeping_policy_id} | Leave types: ".LeaveType::query()->count()." | Team settings: ".count(Support::selectOptions()['team_settings'])."\n\n";

$failed = 0;

foreach (Support::settingsTabs() as $tab => $label) {
    echo "[$tab] ";

    try {
        $validated = Support::validateSettings($tab, $payloads[$tab] ?? []);
        $updatePayload = Support::settingsPayload($tab, $validated);
        $policy->update($updatePayload);
        echo "validate+save OK\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL: ".$e->getMessage()."\n";
    }

    try {
        $response = app()->handle(
            Request::create(
                route(Support::routeName('tab'), ['policy' => $policy->timekeeping_policy_id, 'tab' => $tab]),
                'GET',
            ),
        );
        $status = $response->getStatusCode();
        echo "  GET tab HTTP $status ".($status === 200 ? 'OK' : 'FAIL')."\n";
        if ($status !== 200) {
            $failed++;
        }
    } catch (Throwable $e) {
        $failed++;
        echo "  GET tab FAIL: ".$e->getMessage()."\n";
    }
}

echo "\n--- HTTP PUT via controller ---\n";

$controller = app(\App\Http\Controllers\TimekeepingPolicyController::class);
$leaveTypeId = LeaveType::query()->value('leave_type_id');

foreach (['tardiness-undertime' => [
    'is_allow_flexi_time' => '1',
    'max_flexi_time' => '45',
    'grace_period' => '10',
    'tardiness_leave_type_id' => $leaveTypeId,
    'undertime_leave_type_id' => $leaveTypeId,
]] as $tab => $data) {
    $request = Request::create(
        route(Support::routeName('update'), ['policy' => $policy->timekeeping_policy_id, 'tab' => $tab]),
        'PUT',
        $data,
    );
    $request->setUserResolver(fn () => $user);

    try {
        $response = $controller->updateSettings($request, $policy->timekeeping_policy_id, $tab);
        $policy->refresh();
        echo "[$tab] PUT redirect OK | flexi=".json_encode($policy->is_allow_flexi_time)." max_flexi={$policy->max_flexi_time}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "[$tab] PUT FAIL: ".$e->getMessage()."\n";
    }
}

echo "\n--- Equivalent CRUD (tardiness) ---\n";

try {
    $validated = Support::validateEquivalent('tardiness', [
        'time_from' => '1',
        'time_to' => '15',
        'equivalent' => '0.25',
    ], $policy);
    $payload = Support::equivalentPayload('tardiness', $validated, $policy);
    $config = Support::equivalentConfig('tardiness');
    $record = $config['model']::query()->create($payload);
    echo "create OK id={$record->getKey()}\n";

    $record->update(['equivalent' => '0.5']);
    echo "update OK\n";

    $record->delete();
    echo "delete OK\n";
} catch (Throwable $e) {
    $failed++;
    echo "equivalent FAIL: ".$e->getMessage()."\n";
}

echo "\n--- Policy list GET ---\n";
try {
    $request = Request::create(route(Support::routeName('index')), 'GET');
    $request->setUserResolver(fn () => $user);
    $response = app(\App\Http\Controllers\TimekeepingPolicyController::class)->index($request);
    echo 'policy list view OK ('.$response->name().")\n";
} catch (Throwable $e) {
    $failed++;
    echo 'policy list FAIL: '.$e->getMessage()."\n";
}

echo "\n".($failed === 0 ? 'All tabs passed.' : "$failed failure(s).")."\n";
exit($failed > 0 ? 1 : 0);
