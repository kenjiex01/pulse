<?php

namespace Database\Seeders;

use App\Models\LuIcctOffenseCategory;
use Illuminate\Database\Seeder;

class IcctOffenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'A', 'label' => 'A', 'sort_order' => 1],
            ['code' => 'B', 'label' => 'B', 'sort_order' => 2],
            ['code' => 'C', 'label' => 'C', 'sort_order' => 5],
            ['code' => 'D', 'label' => 'D', 'sort_order' => 7],
        ];

        foreach ($categories as $category) {
            LuIcctOffenseCategory::withTrashed()->updateOrCreate(
                ['code' => $category['code']],
                [
                    'label' => $category['label'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                    'deleted_at' => null,
                ],
            );
        }

        LuIcctOffenseCategory::query()
            ->whereIn('code', ['B - C', 'B - D', 'C - D'])
            ->delete();
    }
}
