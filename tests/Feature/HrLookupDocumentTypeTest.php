<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrLookupDocumentTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_create_document_type_without_sort_order_defaults_to_zero(): void
    {
        $user = User::query()->firstOrFail();

        $response = $this->actingAs($user)->post(route('hr.document-types.store'), [
            'type_code' => 'test',
            'type_name' => 'Test Document Type',
            'description' => 'test',
            'is_required' => '1',
            'is_active' => '1',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('tbl_document_types', [
            'type_code' => 'test',
            'type_name' => 'Test Document Type',
            'sort_order' => 0,
        ]);

        $this->assertSame(0, (int) DocumentType::query()->where('type_code', 'test')->value('sort_order'));
    }
}
