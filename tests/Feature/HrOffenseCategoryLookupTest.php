<?php

namespace Tests\Feature;

use App\Models\LuIcctOffense;
use App\Models\LuIcctOffenseCategory;
use App\Models\LuIcctOffensePenalty;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IcctOffenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrOffenseCategoryLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_offense_categories_is_registered_as_hr_submodule(): void
    {
        $this->assertDatabaseHas('sys_sub_modules', [
            'route_name' => 'hr.offense-categories.index',
            'name' => 'Offense Categories',
            'is_active' => true,
        ]);
    }

    public function test_offense_penalties_lookup_is_hidden_from_menu(): void
    {
        $this->assertFalse(config('hr_lookups.offense-penalties.menu') ?? true);
        $this->assertFalse(config('hr_lookups.offense-frequencies.menu') ?? true);
    }

    public function test_admin_can_view_offense_categories_index(): void
    {
        $category = LuIcctOffenseCategory::query()->where('code', 'A')->firstOrFail();

        $this->actingAs(User::query()->firstOrFail())
            ->get(route('hr.offense-categories.index'))
            ->assertOk()
            ->assertSee('Offense Categories', false)
            ->assertSee('Table of Penalties', false)
            ->assertSee('hr-lookup-edit-offense-categories-'.$category->icct_offense_category_id, false)
            ->assertDontSee('offense categories records', false)
            ->assertSee('Add Category', false)
            ->assertSee('Add Frequency', false)
            ->assertDontSee('B - C', false);

        $this->assertDatabaseHas('lu_icct_offense_categories', [
            'code' => 'A',
            'label' => 'A',
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('lu_icct_offense_categories', [
            'code' => 'D',
            'label' => 'D',
            'sort_order' => 7,
        ]);
    }

    public function test_offense_categories_index_shows_penalty_matrix(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->get(route('hr.offense-categories.index'))
            ->assertOk()
            ->assertSee('Table of Penalties', false)
            ->assertSee('Written Warning', false)
            ->assertSee('First Offense', false);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $category = LuIcctOffenseCategory::query()->where('code', 'A')->firstOrFail();
        $category->update(['is_active' => false]);

        $this->actingAs(User::query()->firstOrFail())
            ->delete(route('hr.offense-categories.destroy', $category->icct_offense_category_id))
            ->assertRedirect(route('hr.offense-categories.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('lu_icct_offense_categories', [
            'icct_offense_category_id' => $category->icct_offense_category_id,
            'deleted_at' => null,
        ]);
    }

    public function test_updating_category_code_syncs_assigned_offenses(): void
    {
        $category = LuIcctOffenseCategory::query()->where('code', 'B')->firstOrFail();
        $offense = LuIcctOffense::query()->where('section_code', 'II.8')->firstOrFail();
        $offense->update(['category' => 'B', 'icct_offense_category_id' => $category->icct_offense_category_id]);

        $this->actingAs(User::query()->firstOrFail())
            ->put(route('hr.offense-categories.update', $category->icct_offense_category_id), [
                'form_context' => 'edit-offense-categories-'.$category->icct_offense_category_id,
                'edit_record_id' => $category->icct_offense_category_id,
                'code' => 'B',
                'label' => 'Category B (updated)',
                'sort_order' => $category->sort_order,
                'is_active' => '1',
            ])
            ->assertRedirect(route('hr.offense-categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lu_icct_offenses', [
            'icct_offense_id' => $offense->icct_offense_id,
            'category' => 'B',
        ]);
    }

    public function test_editing_category_syncs_frequency_penalties(): void
    {
        $category = LuIcctOffenseCategory::query()->where('code', 'A')->firstOrFail();

        $this->actingAs(User::query()->firstOrFail())
            ->put(route('hr.offense-categories.update', $category->icct_offense_category_id), [
                'form_context' => 'edit-offense-categories-'.$category->icct_offense_category_id,
                'edit_record_id' => $category->icct_offense_category_id,
                'code' => 'A',
                'label' => 'A',
                'sort_order' => $category->sort_order,
                'is_active' => '1',
                'penalties' => [
                    1 => 'Verbal Reprimand (updated)',
                    2 => 'Written Warning',
                ],
            ])
            ->assertRedirect(route('hr.offense-categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lu_icct_offense_penalties', [
            'icct_offense_category_id' => $category->icct_offense_category_id,
            'frequency_ordinal' => 1,
            'penalty' => 'Verbal Reprimand (updated)',
        ]);
    }

    public function test_admin_can_add_offense_category_and_frequency(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->post(route('hr.offense-categories.store'), [
                'form_context' => 'create-offense-categories',
                'code' => 'E',
                'label' => 'Category E',
                'sort_order' => 10,
                'is_active' => '1',
                'penalties' => [
                    1 => 'Written Warning',
                ],
            ])
            ->assertRedirect(route('hr.offense-categories.index'))
            ->assertSessionHas('success');

        $this->actingAs(User::query()->firstOrFail())
            ->post(route('hr.offense-frequencies.store'), [
                'form_context' => 'create-offense-frequencies',
                'label' => 'Seventh Offense',
                'sort_order' => 7,
                'is_active' => '1',
            ])
            ->assertRedirect(route('hr.offense-categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lu_icct_offense_categories', [
            'code' => 'E',
            'label' => 'Category E',
        ]);
        $this->assertDatabaseHas('lu_icct_offense_frequencies', [
            'label' => 'Seventh Offense',
            'frequency_ordinal' => 7,
        ]);
    }

    public function test_category_seeder_is_idempotent(): void
    {
        $this->seed(IcctOffenseCategorySeeder::class);
        $this->seed(IcctOffenseCategorySeeder::class);

        $this->assertSame(4, LuIcctOffenseCategory::query()->count());
        $this->assertSame(
            ['A', 'B', 'C', 'D'],
            LuIcctOffenseCategory::query()->orderBy('sort_order')->pluck('code')->all(),
        );
    }
}
