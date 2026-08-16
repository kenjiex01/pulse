<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $documentTypes = [
            [
                'type_code' => 'ID_1X1',
                'type_name' => '1x1 ID Photo',
                'description' => '1x1 ID photograph',
                'sort_order' => 1,
            ],
            [
                'type_code' => 'TIN',
                'type_name' => 'TIN ID',
                'description' => 'Valid TIN (Tax Identification Number) ID',
                'sort_order' => 2,
            ],
            [
                'type_code' => 'SSS',
                'type_name' => 'SSS ID',
                'description' => 'Valid SSS ID',
                'sort_order' => 3,
            ],
            [
                'type_code' => 'PHILHEALTH',
                'type_name' => 'PhilHealth ID',
                'description' => 'Valid PhilHealth ID',
                'sort_order' => 4,
            ],
            [
                'type_code' => 'PAGIBIG',
                'type_name' => 'Pag-IBIG ID',
                'description' => 'Valid Pag-IBIG ID',
                'sort_order' => 5,
            ],
            [
                'type_code' => 'PRC_ID',
                'type_name' => 'PRC ID',
                'description' => 'Valid PRC ID / license card',
                'sort_order' => 6,
            ],
            [
                'type_code' => 'ADDR_PROOF',
                'type_name' => 'Provincial and Present Address with Proof of Billing',
                'description' => 'Provincial and present address with proof of billing. If tenant, additional valid ID of landlord may be required.',
                'sort_order' => 7,
            ],
            [
                'type_code' => 'MOBILE',
                'type_name' => 'Updated Cellphone Numbers',
                'description' => 'Updated cellphone / contact numbers',
                'sort_order' => 8,
            ],
            [
                'type_code' => 'BIRTH_CERT',
                'type_name' => 'Birth Certificate',
                'description' => 'PSA / NSO birth certificate',
                'sort_order' => 9,
            ],
            [
                'type_code' => 'MARRIAGE_CERT',
                'type_name' => 'Marriage Certificate (if applicable)',
                'description' => 'Marriage certificate, if applicable',
                'sort_order' => 10,
            ],
            [
                'type_code' => 'CHILD_BIRTH',
                'type_name' => "Child's Birth Certificate (if applicable)",
                'description' => "Child's birth certificate, if applicable",
                'sort_order' => 11,
            ],
            [
                'type_code' => 'DIPLOMA_TOR',
                'type_name' => 'Diploma and TOR (Tertiary, Masters, and PhD)',
                'description' => 'Diploma and Transcript of Records for tertiary, masters, and PhD',
                'sort_order' => 12,
            ],
            [
                'type_code' => 'PRC_RATING',
                'type_name' => 'PRC Rating Sheet',
                'description' => 'PRC rating sheet',
                'sort_order' => 13,
            ],
            [
                'type_code' => 'COE',
                'type_name' => 'Certificate of Employment',
                'description' => 'Certificate of employment(s)',
                'sort_order' => 14,
            ],
            [
                'type_code' => 'TRAINING_CERT',
                'type_name' => 'Training Certificates',
                'description' => 'Training certificates',
                'sort_order' => 15,
            ],
            [
                'type_code' => 'TESDA_CERT',
                'type_name' => 'Professional / TESDA Certificates',
                'description' => 'Professional and/or TESDA certificates',
                'sort_order' => 16,
            ],
        ];

        foreach ($documentTypes as $documentType) {
            $existing = DocumentType::withTrashed()
                ->where(function ($query) use ($documentType) {
                    $query->where('type_code', $documentType['type_code'])
                        ->orWhere('type_name', $documentType['type_name']);
                })
                ->first();

            $attributes = array_merge($documentType, [
                'is_required' => true,
                'is_active' => true,
            ]);

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->forceFill($attributes)->save();

                continue;
            }

            DocumentType::query()->create($attributes);
        }

        $keepCodes = collect($documentTypes)->pluck('type_code')->all();
        $retiredCodes = [
            'RESUME',
            'CONTRACT',
            'ID',
            'TOR',
            'CERT',
            'NBI',
            'GOV_IDS',
        ];

        DocumentType::query()
            ->whereNotNull('type_code')
            ->whereNotIn('type_code', $keepCodes)
            ->whereIn('type_code', $retiredCodes)
            ->get()
            ->each(function (DocumentType $type) {
                $type->delete();
            });
    }
}
