<?php

namespace Tests\Feature;

use App\Models\LuIcctOffense;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IcctOffenseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrNatureOfOffenseLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_nature_of_offense_is_registered_as_hr_submodule(): void
    {
        $this->assertDatabaseHas('sys_sub_modules', [
            'route_name' => 'hr.nature-of-offenses.index',
            'name' => 'Nature of Offense',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_nature_of_offense_index(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->get(route('hr.nature-of-offenses.index'))
            ->assertOk()
            ->assertSee('Nature of Offense', false)
            ->assertSee('I.1', false)
            ->assertSee('Add Nature of Offense', false);
    }

    public function test_admin_can_create_a_nature_of_offense(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->post(route('hr.nature-of-offenses.store'), [
                'form_context' => 'create-nature-of-offenses',
                'heading_roman' => 'VIII',
                'section_number' => 99,
                'nature_of_offense' => 'Custom test offense added from HR lookup.',
                'category' => 'A',
                'is_active' => '1',
            ])
            ->assertRedirect(route('hr.nature-of-offenses.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lu_icct_offenses', [
            'section_code' => 'VIII.99',
            'heading_roman' => 'VIII',
            'section_number' => 99,
            'nature_of_offense' => 'Custom test offense added from HR lookup.',
            'category' => 'A',
            'is_active' => 1,
        ]);

        $offense = LuIcctOffense::query()->where('section_code', 'VIII.99')->firstOrFail();
        $this->assertSame(LuIcctOffense::HEADING_LABELS['VIII'], $offense->heading_label);
    }

    public function test_duplicate_heading_and_section_is_rejected(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->from(route('hr.nature-of-offenses.index'))
            ->post(route('hr.nature-of-offenses.store'), [
                'form_context' => 'create-nature-of-offenses',
                'heading_roman' => 'I',
                'section_number' => 1,
                'nature_of_offense' => 'Duplicate of seeded I.1',
                'category' => 'D',
                'is_active' => '1',
            ])
            ->assertRedirect(route('hr.nature-of-offenses.index'))
            ->assertSessionHasErrors('section_number');
    }

    public function test_admin_can_edit_a_nature_of_offense(): void
    {
        $offense = LuIcctOffense::query()->where('section_code', 'II.8')->firstOrFail();
        $this->actingAs(User::query()->firstOrFail())
            ->put(route('hr.nature-of-offenses.update', $offense->icct_offense_id), [
                'form_context' => 'edit-nature-of-offenses-'.$offense->icct_offense_id,
                'edit_record_id' => $offense->icct_offense_id,
                'heading_roman' => $offense->heading_roman,
                'section_number' => $offense->section_number,
                'nature_of_offense' => 'Updated AWOL wording for testing.',
                'category' => 'B',
                'sort_order' => $offense->sort_order,
                'is_active' => '1',
            ])
            ->assertRedirect(route('hr.nature-of-offenses.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lu_icct_offenses', [
            'icct_offense_id' => $offense->icct_offense_id,
            'section_code' => 'II.8',
            'nature_of_offense' => 'Updated AWOL wording for testing.',
            'category' => 'B',
        ]);
    }

    public function test_admin_can_deactivate_and_soft_delete_a_nature_of_offense(): void
    {
        $offense = LuIcctOffense::query()->where('section_code', 'I.15')->firstOrFail();

        $this->actingAs(User::query()->firstOrFail())
            ->post(route('hr.nature-of-offenses.toggle-status', $offense->icct_offense_id))
            ->assertRedirect(route('hr.nature-of-offenses.index'));

        $this->assertFalse($offense->fresh()->is_active);

        $this->actingAs(User::query()->firstOrFail())
            ->delete(route('hr.nature-of-offenses.destroy', $offense->icct_offense_id))
            ->assertRedirect(route('hr.nature-of-offenses.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('lu_icct_offenses', [
            'icct_offense_id' => $offense->icct_offense_id,
        ]);
    }

    public function test_catalog_seeder_does_not_overwrite_edited_offenses(): void
    {
        $offense = LuIcctOffense::query()->where('section_code', 'I.1')->firstOrFail();
        $offense->update(['nature_of_offense' => 'User-edited nature of offense.']);

        $this->seed(IcctOffenseSeeder::class);

        $this->assertSame(
            'User-edited nature of offense.',
            $offense->fresh()->nature_of_offense,
        );
    }

    public function test_inactive_offenses_are_omitted_from_memo_dropdown_catalog(): void
    {
        $offense = LuIcctOffense::query()->where('section_code', 'I.1')->firstOrFail();
        $offense->update(['is_active' => false]);

        $labels = LuIcctOffense::natureDropdownLabels();

        $this->assertFalse(
            collect($labels)->contains(fn (string $label) => str_starts_with($label, 'I.1 — ')),
        );
        $this->assertNotContains($offense->icct_offense_id, LuIcctOffense::catalogOrdered()->pluck('icct_offense_id'));
        $this->assertContains(
            $offense->icct_offense_id,
            LuIcctOffense::catalogForSelection([$offense->icct_offense_id])->pluck('icct_offense_id'),
        );
    }
}
