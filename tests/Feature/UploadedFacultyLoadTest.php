<?php

namespace Tests\Feature;

use App\Models\PulseFacultyLoadUpload;
use App\Models\User;
use App\Services\SkolarisApiService;
use App\Support\TimeLogs;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UploadedFacultyLoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function teaching_loads_uploaded_tab_renders_pull_action(): void
    {
        $user = User::query()->firstOrFail();

        $response = $this->actingAs($user)->get(route(TimeLogs::routeName('tab'), [
            'tab' => TimeLogs::TEACHING_LOADS_TAB,
            'load_source' => TimeLogs::LOAD_SOURCE_UPLOADED,
        ]));

        $response->assertOk();
        $response->assertSee('Uploaded PDFs');
        $response->assertSee('No uploaded loads pulled yet');
        $response->assertSee('Pull from Skolaris', false);
        $response->assertDontSee('Upload PDF', false);
    }

    #[Test]
    public function uploaded_faculty_load_pull_syncs_from_skolaris(): void
    {
        $user = User::query()->firstOrFail();

        $mock = Mockery::mock(SkolarisApiService::class);
        $mock->shouldReceive('listUploadedFacultyLoads')
            ->once()
            ->andReturn([
                'data' => [[
                    'upload_id' => 501,
                    'faculty_name' => 'Jane Faculty',
                    'original_filename' => 'loading.pdf',
                    'parse_status' => 'parsed',
                    'updated_at' => '2026-09-10T10:00:00+08:00',
                ]],
                'meta' => ['last_page' => 1],
            ]);
        $mock->shouldReceive('getUploadedFacultyLoad')
            ->once()
            ->with(501)
            ->andReturn([
                'upload_id' => 501,
                'faculty_name' => 'Jane Faculty',
                'original_filename' => 'loading.pdf',
                'parse_status' => 'parsed',
                'updated_at' => '2026-09-10T10:00:00+08:00',
                'uploaded_by' => ['full_name' => 'Skolaris Admin', 'email' => 'admin@example.com'],
                'items' => [[
                    'row_number' => 1,
                    'row_type' => 'subject',
                    'subject_code' => 'CS101',
                    'title' => 'Intro to CS',
                ]],
            ]);
        $mock->shouldReceive('downloadUploadedFacultyLoadBinary')
            ->once()
            ->with(501)
            ->andReturn('%PDF-1.4 test');

        $this->app->instance(SkolarisApiService::class, $mock);

        $response = $this->actingAs($user)->post(route(TimeLogs::routeName('uploads.pull')), [
            'parse_status' => 'all',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('pulse_faculty_load_uploads', [
            'skolaris_upload_id' => 501,
            'faculty_name' => 'Jane Faculty',
            'parse_status' => 'parsed',
            'pulled_by_id' => $user->id,
        ]);

        $upload = PulseFacultyLoadUpload::query()->where('skolaris_upload_id', 501)->firstOrFail();
        $this->assertSame(1, $upload->items()->count());
    }

    #[Test]
    public function uploaded_faculty_load_can_be_soft_deleted_locally(): void
    {
        $user = User::query()->firstOrFail();

        $upload = PulseFacultyLoadUpload::query()->create([
            'skolaris_upload_id' => 777,
            'faculty_name' => 'Jane Faculty',
            'original_filename' => 'sample.pdf',
            'stored_path' => 'pulse/faculty-loads/skolaris/777.pdf',
            'parse_status' => 'parsed',
            'pulled_by_id' => $user->id,
            'pulled_at' => now(),
        ]);

        $response = $this->actingAs($user)->delete(route(TimeLogs::routeName('uploads.destroy'), $upload));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('pulse_faculty_load_uploads', [
            'upload_id' => $upload->upload_id,
        ]);
    }
}
