<?php

namespace Database\Seeders;

use App\Models\LuTemplate;
use Illuminate\Database\Seeder;

class LuTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $records = [
            ['template_id' => 1, 'template' => 'Approved Forms'],
            ['template_id' => 2, 'template' => 'Filed Forms by Employee'],
            ['template_id' => 3, 'template' => 'Filed Forms by Immediate Superior'],
            ['template_id' => 4, 'template' => 'Cancelled Forms by Employee'],
            ['template_id' => 5, 'template' => 'Cancelled Forms by Immediate Superior'],
            ['template_id' => 6, 'template' => 'Disapproved Form'],
        ];

        foreach ($records as $record) {
            LuTemplate::query()->updateOrCreate(
                ['template_id' => $record['template_id']],
                ['template' => $record['template']],
            );
        }
    }
}
