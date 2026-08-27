<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeSkolarisSyncService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmployeeSkolarisSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        config([
            'skolaris.pulse_api_key' => 'test-pulse-key',
            'skolaris.pulse_api_base_url' => 'https://skolaris.test/pulse-api/v1',
        ]);
    }

    public function test_pending_groups_updates_by_pulse_employee_id(): void
    {
        $employee = $this->createPulseEmployee([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'phone' => '09170000000',
        ]);

        $this->fakeLocalUpdates([
            $this->pendingUpdate(10, $employee->employee_id, 'phone', '09171234567'),
            $this->pendingUpdate(11, $employee->employee_id, 'city_municipality', 'Cainta'),
            $this->pendingUpdate(12, 999999, 'phone', '09179999999', [
                'employee_name' => 'Pedro Santos',
            ]),
        ]);

        $response = $this->actingAs(User::query()->firstOrFail())
            ->getJson(route('employees.sync.pending'));

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(2, $response->json('count'));

        $byKey = collect($response->json('employees'))->keyBy('employee_number');
        $this->assertTrue($byKey[(string) $employee->employee_id]['can_sync']);
        $this->assertSame('changed', $byKey[(string) $employee->employee_id]['kind']);
        $this->assertSame($employee->full_name, $byKey[(string) $employee->employee_id]['name']);
        $this->assertFalse($byKey['999999']['can_sync']);
        $this->assertSame('unmatched', $byKey['999999']['kind']);
        $this->assertSame('Pedro Santos', $byKey['999999']['name']);
    }

    public function test_pending_matches_pulse_employee_number_and_shows_full_name(): void
    {
        $this->createPulseEmployee([
            'employee_number' => '287',
            'first_name' => 'Ana',
            'middle_name' => 'Cruz',
            'last_name' => 'Reyes',
        ]);

        $this->fakeLocalUpdates([
            $this->pendingUpdate(20, 287, 'phone', '09171230000'),
        ]);

        $response = $this->actingAs(User::query()->firstOrFail())
            ->getJson(route('employees.sync.pending'));

        $response->assertOk();
        $row = collect($response->json('employees'))->firstWhere('employee_number', '287');
        $this->assertNotNull($row);
        $this->assertTrue($row['can_sync']);
        $this->assertSame('changed', $row['kind']);
        $this->assertSame('Ana Cruz Reyes', $row['name']);
    }

    public function test_pending_uses_timekeeping_full_name_when_not_in_pulse(): void
    {
        $this->fakeLocalUpdates(
            [$this->pendingUpdate(30, 344, 'phone', '09171234400')],
            [
                '344' => [
                    'employee_id' => 344,
                    'employee_number' => '8670',
                    'full_name' => 'Livine Joy Aboguin Estoya',
                ],
            ],
        );

        $response = $this->actingAs(User::query()->firstOrFail())
            ->getJson(route('employees.sync.pending'));

        $response->assertOk();
        $row = collect($response->json('employees'))->firstWhere('employee_number', '344');
        $this->assertNotNull($row);
        $this->assertFalse($row['can_sync']);
        $this->assertSame('Livine Joy Aboguin Estoya', $row['name']);
    }

    public function test_pending_matches_pulse_using_timekeeping_employee_number(): void
    {
        $this->createPulseEmployee([
            'employee_number' => '8670',
            'first_name' => 'Livine',
            'middle_name' => 'Joy',
            'last_name' => 'Estoya',
        ]);

        $this->fakeLocalUpdates(
            [$this->pendingUpdate(31, 344, 'phone', '09171234400')],
            [
                '344' => [
                    'employee_id' => 344,
                    'employee_number' => '8670',
                    'full_name' => 'Livine Joy Aboguin Estoya',
                ],
            ],
        );

        $response = $this->actingAs(User::query()->firstOrFail())
            ->getJson(route('employees.sync.pending'));

        $response->assertOk();
        $row = collect($response->json('employees'))->firstWhere('employee_number', '344');
        $this->assertNotNull($row);
        $this->assertTrue($row['can_sync']);
        $this->assertSame('changed', $row['kind']);
        $this->assertSame('Livine Joy Estoya', $row['name']);
    }

    public function test_preview_returns_field_changes_for_employee_id(): void
    {
        $employee = $this->createPulseEmployee(['first_name' => 'Old']);

        $this->fakeLocalUpdates([
            $this->pendingUpdate(10, $employee->employee_id, 'first_name', 'Juan'),
        ]);

        $this->actingAs(User::query()->firstOrFail())
            ->getJson(route('employees.sync.pending'))
            ->assertOk();

        $preview = $this->actingAs(User::query()->firstOrFail())
            ->getJson(route('employees.sync.preview', [
                'employee_number' => (string) $employee->employee_id,
            ]));

        $preview->assertOk()->assertJsonPath('ok', true);
        $fields = collect($preview->json('changes'))->pluck('field')->all();
        $this->assertContains('first_name', $fields);
    }

    public function test_apply_updates_pulse_employee_and_marks_applied(): void
    {
        $employee = $this->createPulseEmployee([
            'first_name' => 'Old',
            'pagibig_number' => null,
        ]);

        $this->fakeLocalUpdates([
            $this->pendingUpdate(44, $employee->employee_id, 'first_name', 'Juan'),
            $this->pendingUpdate(45, $employee->employee_id, 'pagibig_number', '1213-8073-5594'),
        ]);

        $user = User::query()->firstOrFail();
        $this->actingAs($user)->getJson(route('employees.sync.pending'))->assertOk();

        $apply = $this->actingAs($user)->postJson(route('employees.sync.apply'), [
            'employee_numbers' => [(string) $employee->employee_id],
        ]);

        $apply->assertOk()
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('failed', []);

        $employee->refresh();
        $this->assertSame('Juan', $employee->first_name);
        $this->assertSame('1213-8073-5594', $employee->pagibig_number);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), 'local-employee-updates/mark-applied')
                && $request['update_ids'] === [44, 45];
        });
    }

    public function test_index_shows_approve_button(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Approve')
            ->assertSee('Approve all')
            ->assertSee('Approve selected')
            ->assertSee('employee-skolaris-sync-modal');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPulseEmployee(array $overrides = []): Employee
    {
        $campus = Campus::query()->where('campus_code', 'CA')->firstOrFail();

        return Employee::query()->create(array_merge([
            'campus_id' => $campus->campus_id,
            'campus' => 'CA',
            'employee_number' => 'CA-SYNC-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => 'sync.'.uniqid().'@example.com',
            'employment_status' => Employee::STATUS_ACTIVE,
            'compliance_status' => Employee::COMPLIANCE_PENDING,
        ], $overrides));
    }

    /**
     * @param  array<int, array<string, mixed>>  $updates
     * @param  array<string, array<string, mixed>>  $timekeepingCards
     */
    private function fakeLocalUpdates(array $updates, array $timekeepingCards = []): void
    {
        Http::fake(function ($request) use ($updates, $timekeepingCards) {
            $url = $request->url();

            if (preg_match('#timekeeping/employees/(\d+)/attendance#', $url, $matches) === 1) {
                $id = $matches[1];
                $card = $timekeepingCards[$id] ?? null;
                if ($card === null) {
                    return Http::response(['success' => false, 'message' => 'Employee not found'], 404);
                }

                return Http::response([
                    'success' => true,
                    'message' => 'Timekeeping attendance generated successfully',
                    'data' => ['employee' => $card],
                ]);
            }

            if (str_contains($url, 'local-employee-updates/mark-applied')) {
                return Http::response([
                    'success' => true,
                    'message' => 'Updates marked as applied',
                    'data' => [],
                ]);
            }

            if (str_contains($url, 'local-employee-updates')) {
                return Http::response([
                    'success' => true,
                    'message' => 'Local employee updates retrieved successfully',
                    'data' => $updates,
                ]);
            }

            return Http::response(['success' => false, 'message' => 'Unexpected URL '.$url], 404);
        });

        app(EmployeeSkolarisSyncService::class)->forgetPendingCache();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function pendingUpdate(int $updateId, int $employeeId, string $field, ?string $newValue, array $extra = []): array
    {
        return array_merge([
            'update_id' => $updateId,
            'employee_id' => (string) $employeeId,
            'field_name' => $field,
            'previous_value' => null,
            'new_value' => $newValue,
            'status' => 'pending',
            'applied_at' => null,
        ], $extra);
    }
}
