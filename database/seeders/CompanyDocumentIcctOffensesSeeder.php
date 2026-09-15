<?php

namespace Database\Seeders;

use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\LuIcctOffense;
use App\Models\LuIcctOffensePenalty;
use Illuminate\Database\Seeder;

/**
 * ICCT Colleges Code of Offenses and Table of Penalties (effective February 16, 2011).
 * Idempotent by form code. Does not overwrite designer-edited elements.
 */
class CompanyDocumentIcctOffensesSeeder extends Seeder
{
    /** @var list<string> */
    private const OFFENSE_BLOCK_FORM_CODES = [
        'hr_notice_to_explain',
        'hr_verbal_reprimand',
        'hr_written_warning',
        'hr_notice_of_suspension',
        'hr_notice_of_dismissal',
    ];

    public function run(): void
    {
        $sort = 10;

        $this->seedForm('hr_notice_to_explain', [
            'name' => 'Notice to Explain (NTE)',
            'description' => 'Due-process notice under the ICCT Code of Offenses. The employee is given the opportunity to explain and submit evidence of mitigating circumstances.',
            'submit_label' => 'Issue NTE',
            'success_message' => 'Notice to Explain has been recorded.',
            'sort_order' => $sort++,
        ], $this->noticeToExplainElements());

        $this->seedForm('hr_verbal_reprimand', [
            'name' => 'Verbal Reprimand Record',
            'description' => 'Category A first offense — verbal reprimand recorded for reference (Code of Offenses, Table of Penalties).',
            'submit_label' => 'Record Reprimand',
            'success_message' => 'Verbal reprimand has been recorded.',
            'sort_order' => $sort++,
        ], $this->verbalReprimandElements());

        $this->seedForm('hr_written_warning', [
            'name' => 'Written Warning',
            'description' => 'Written warning under the ICCT Table of Penalties (Category A second offense or Category B first offense).',
            'submit_label' => 'Issue Warning',
            'success_message' => 'Written warning has been recorded.',
            'sort_order' => $sort++,
        ], $this->writtenWarningElements());

        $this->seedForm('hr_notice_of_suspension', [
            'name' => 'Notice of Suspension',
            'description' => 'Suspension notice (2, 3, 4, 5, or 7 working days). Implementation may be delayed by the concerned Vice-President for up to 30 days.',
            'submit_label' => 'Issue Suspension',
            'success_message' => 'Notice of suspension has been recorded.',
            'sort_order' => $sort++,
        ], $this->suspensionElements());

        $this->seedForm('hr_notice_of_dismissal', [
            'name' => 'Notice of Dismissal',
            'description' => 'Dismissal under Category D or progressive penalties. Dismissal should be implemented within 5 working days. Due process must be observed.',
            'submit_label' => 'Issue Dismissal',
            'success_message' => 'Notice of dismissal has been recorded.',
            'sort_order' => $sort++,
        ], $this->dismissalElements());

        $this->seedForm('hr_return_to_work', [
            'name' => 'Return to Work Form',
            'description' => 'Appendix B of the ICCT Code of Offenses. Required after leave expiration or AWOL; HR verifies supporting documents and recommends approval to the Vice-President.',
            'submit_label' => 'Submit Return to Work',
            'success_message' => 'Return to Work form has been submitted.',
            'sort_order' => $sort++,
        ], $this->returnToWorkElements());

        $this->seedForm('hr_awol_notice', [
            'name' => 'Notice of Absence Without Official Leave (AWOL)',
            'description' => 'Administration II.7–II.9. Half-day or whole-day AWOL is Category B; five (5) consecutive days or more is Category D (dismissal).',
            'submit_label' => 'Issue AWOL Notice',
            'success_message' => 'AWOL notice has been recorded.',
            'sort_order' => $sort++,
        ], $this->awolNoticeElements());

        $this->seedForm('hr_uniform_infraction', [
            'name' => 'Uniform / ID / Nameplate Infraction',
            'description' => 'Administration II.20 and Appendix A. Complete uniform includes prescribed attire, closed-front black shoes (female) or completely closed black shoes (male), ID, and nameplate. Salary deductions apply in addition to disciplinary action.',
            'submit_label' => 'Record Infraction',
            'success_message' => 'Uniform infraction has been recorded.',
            'sort_order' => $sort++,
        ], $this->uniformInfractionElements());

        $this->syncOffenseNatureDropdowns();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<array<string, mixed>>  $elements
     */
    private function seedForm(string $code, array $meta, array $elements): void
    {
        $form = CompanyDocumentForm::query()->updateOrCreate(
            ['code' => $code],
            array_merge($meta, [
                'document_type' => CompanyDocumentForm::TYPE_MEMO,
                'allow_multiple_submissions' => true,
                'requires_nte' => $meta['requires_nte'] ?? false,
                'is_nte' => $meta['is_nte'] ?? ($code === 'hr_notice_to_explain'),
                'is_active' => true,
                'version' => 1,
            ]),
        );

        if ($form->elements()->exists()) {
            return;
        }

        foreach ($elements as $index => $element) {
            CompanyDocumentElement::query()->create(array_merge([
                'company_document_form_id' => $form->company_document_form_id,
                'width' => 'full',
                'sort_order' => $index,
                'settings_json' => ['label_align' => 'top'],
            ], $element));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function noticeToExplainElements(): array
    {
        return array_merge(
            [
                $this->heading('ICCT Colleges Foundation, Inc.'),
                $this->heading('Notice to Explain'),
                $this->paragraph('Pursuant to the Code of Offenses and Table of Penalties (effective February 16, 2011), you are directed to explain in writing the incident described below. Due process will be observed. You will be given all opportunity to provide evidence of mitigating circumstances. The burden of proof lies with the employee who committed the offense.'),
            ],
            $this->addresseeBlock(),
            $this->offenseBlock(),
            [
                $this->date('Reply deadline', 'reply_deadline', true),
                $this->longText('Employee written explanation', 'employee_explanation', false, 'State facts, evidence, and any mitigating circumstances.'),
                $this->paragraph($this->penaltyTableText()),
                $this->signature('HR / Immediate Superior Signature', 'issuer_signature'),
                $this->signature('Employee acknowledgment', 'employee_acknowledgment'),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function verbalReprimandElements(): array
    {
        return array_merge(
            [
                $this->heading('Verbal Reprimand Record'),
                $this->paragraph('Category A — First Offense. Verbal reprimand (but recorded for reference) per the Table of Penalties.'),
            ],
            $this->addresseeBlock(),
            $this->offenseBlock(),
            [
                $this->longText('Details of counseling / verbal reprimand', 'reprimand_details', true),
                $this->date('Date reprimand was given', 'reprimand_date', true),
                $this->signature('HR / Supervisor Signature', 'issuer_signature'),
                $this->signature('Employee acknowledgment', 'employee_acknowledgment'),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function writtenWarningElements(): array
    {
        return array_merge(
            [
                $this->heading('Written Warning'),
                $this->paragraph('This written warning is issued under the ICCT Table of Penalties. A further offense in the same category may result in suspension or dismissal as provided in the Code.'),
            ],
            $this->addresseeBlock(),
            $this->offenseBlock(),
            [
                $this->longText('Warning message', 'warning_body', true, 'Describe the violation, prior record if any, and expected corrective action.'),
                $this->paragraph($this->penaltyTableText()),
                $this->signature('HR / Immediate Superior Signature', 'issuer_signature'),
                $this->signature('Employee acknowledgment', 'employee_acknowledgment'),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function suspensionElements(): array
    {
        return array_merge(
            [
                $this->heading('Notice of Suspension'),
                $this->paragraph('You are hereby suspended without pay in accordance with the Code of Offenses and Table of Penalties. The concerned Vice-President may delay implementation of the suspension penalty but only within 30 days.'),
            ],
            $this->addresseeBlock(),
            $this->offenseBlock(),
            [
                $this->dropdown('Length of suspension', 'suspension_days', [
                    '2 Working Days Suspension',
                    '3 Working Days Suspension',
                    '4 Working Days Suspension',
                    '5 Working Days Suspension',
                    '7 Working Days Suspension',
                ], true),
                $this->date('Suspension start date', 'suspension_start', true),
                $this->date('Suspension end date', 'suspension_end', true),
                $this->date('Return-to-work date', 'return_to_work_date', true),
                $this->longText('Particulars', 'suspension_particulars', true),
                $this->paragraph($this->penaltyTableText()),
                $this->signature('HR / Immediate Superior Signature', 'issuer_signature'),
                $this->signature('Employee acknowledgment', 'employee_acknowledgment'),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dismissalElements(): array
    {
        return array_merge(
            [
                $this->heading('Notice of Dismissal'),
                $this->paragraph('In cases of dismissal, the penalty should be implemented within 5 working days. The imposition of penalties is without prejudice to the institution of the appropriate criminal action when warranted by the nature of the offense. Due process will be observed.'),
            ],
            $this->addresseeBlock(),
            $this->offenseBlock(),
            [
                $this->date('Effectivity date of dismissal', 'dismissal_effective_date', true),
                $this->longText('Grounds and particulars', 'dismissal_particulars', true),
                $this->paragraph($this->penaltyTableText()),
                $this->signature('HR / Authorized Officer Signature', 'issuer_signature'),
                $this->signature('Employee acknowledgment', 'employee_acknowledgment'),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function returnToWorkElements(): array
    {
        return [
            $this->heading('ICCT Colleges Foundation, Inc.'),
            $this->heading('Return To Work Form'),
            $this->paragraph('Appendix B — Offenses Against Administration. Report to HR, attach supporting documents, and obtain the Vice-President’s action. Absence of supporting documents means disapproval of a leave extension, which requires another disciplinary action.'),
            $this->shortText('Name', 'employee_name', true),
            $this->shortText('Date/s of Absence', 'absence_dates', true),
            $this->date('Date Reported', 'date_reported', true),
            $this->longText('Reason', 'reason', true),
            $this->shortText('Documents attached', 'documents_attached', false, 'Medical certificate, Return to Work attachments, etc.'),
            $this->longText('Recommendation of HR Representative', 'hr_recommendation', true),
            $this->radio('Vice-President action', 'vp_action', [
                ['label' => 'Approve', 'value' => 'approve'],
                ['label' => 'Disapprove', 'value' => 'disapprove'],
            ], true),
            $this->longText('Remarks of concerned Vice-President', 'vp_remarks', false),
            $this->signature('HR Representative Signature', 'hr_signature'),
            $this->signature('Vice-President Signature', 'vp_signature'),
            $this->signature('Employee Signature', 'employee_signature'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function awolNoticeElements(): array
    {
        return array_merge(
            [
                $this->heading('Notice of Absence Without Official Leave (AWOL)'),
                $this->paragraph('Administration II.7–II.9 and Appendix A. An employee who wishes to be absent must file leave with the favorable recommendation of the immediate head at least 7 working days before the intended leave. In extraordinary circumstances (sudden serious illness), the employee must telephone the President or Vice-President within the first hour of School time. Failure to inform automatically means a disciplinary action. Upon return, submit the Return to Work form with medical certificates. No medical certificate, no official leave. Each day of absence without notice is on an accrual basis. Five (5) consecutive days or more is Category D (dismissal).'),
            ],
            $this->addresseeBlock(),
            [
                $this->dropdown('AWOL type', 'awol_type', [
                    'Half-day AWOL (II.7) — Category B',
                    'Whole-day AWOL (II.8) — Category B',
                    'Failure to return after leave (II.1) — Category B',
                    'AWOL five consecutive days or more (II.9) — Category D',
                ], true),
                $this->shortText('Date/s of AWOL', 'awol_dates', true),
                $this->number('Number of days (accrual)', 'awol_days', true),
                $this->dropdown('Offense frequency', 'offense_frequency', $this->frequencyChoices(), true),
                $this->dropdown('Penalty per Table of Penalties', 'penalty', $this->penaltyChoices(), true),
                $this->yesNo('Notified President / Vice-President within first hour?', 'notified_management', true),
                $this->longText('Particulars', 'awol_particulars', true),
                $this->signature('HR Signature', 'issuer_signature'),
                $this->signature('Employee acknowledgment', 'employee_acknowledgment'),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function uniformInfractionElements(): array
    {
        return array_merge(
            [
                $this->heading('Uniform / ID / Nameplate Infraction'),
                $this->paragraph('Administration II.20 and Appendix A. Complete uniform includes appropriate footwear (closed-front black shoes for female employees; completely closed black shoes for male), Identification Card, and Nameplate. An employee who reports without the prescribed uniform may be refused entry. Management Team members are exempt from the uniform policy but must wear corporate attire and/or appropriate footwear. Faculty tardiness: only 1 tardiness is counted per day, not per hour.'),
            ],
            $this->addresseeBlock(),
            [
                $this->date('Date of infraction', 'infraction_date', true),
                $this->checkbox('Items not complied with', 'infractions', [
                    ['label' => 'Not in uniform — ₱50.00 (upper/lower) or ₱100.00 (whole)', 'value' => 'not_in_uniform'],
                    ['label' => 'No ID — ₱50.00', 'value' => 'no_id'],
                    ['label' => 'No nameplate — ₱50.00', 'value' => 'no_nameplate'],
                    ['label' => 'Inappropriate footwear — ₱50.00', 'value' => 'inappropriate_footwear'],
                ], true),
                $this->number('Salary deduction amount (PHP)', 'deduction_amount', true),
                $this->dropdown('Offense frequency (Category A)', 'offense_frequency', $this->frequencyChoices(), true),
                $this->dropdown('Disciplinary penalty', 'penalty', $this->penaltyChoices(), true),
                $this->longText('Remarks', 'remarks', false),
                $this->signature('HR / Guard / Supervisor Signature', 'issuer_signature'),
                $this->signature('Employee acknowledgment', 'employee_acknowledgment'),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function addresseeBlock(): array
    {
        return [
            $this->shortText('Memo To', 'memo_to', true),
            $this->shortText('Memo From', 'memo_from', true),
            $this->date('Date', 'memo_date', true),
            $this->mergeTag('employee_full_name'),
            $this->mergeTag('employee_number'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function offenseBlock(): array
    {
        return [
            $this->dropdown('Offense heading', 'offense_heading', [
                'I. Offenses Against Company Interest and Policy',
                'II. Offenses Against Administration',
                'III. Offenses Against Authority',
                'IV. Offenses Against Persons',
                'V. Offenses Against Property',
                'VI. Offenses Against Decency, Good Customs or Ethics',
                'VII. Offenses Against Security and Public Order',
                'VIII. Others (repeat infractions within 12 months)',
            ], true),
            $this->shortText('Section number', 'offense_section', true, 'Example: II.8 or I.11'),
            $this->offenseNatureDropdown(),
            $this->dropdown('Category', 'offense_category', [
                'A',
                'B',
                'C',
                'D',
                'B - C',
                'B - D',
                'C - D',
            ], true),
            $this->dropdown('Frequency', 'offense_frequency', $this->frequencyChoices(), true),
            $this->dropdown('Penalty', 'penalty', $this->penaltyChoices(), true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function offenseNatureDropdown(): array
    {
        return $this->dropdown(
            'Nature of offense',
            'nature_of_offense',
            $this->offenseNatureChoices(),
            true,
        );
    }

    /**
     * @return list<string>
     */
    private function offenseNatureChoices(): array
    {
        $fromDb = LuIcctOffense::natureDropdownLabels();

        if ($fromDb !== []) {
            return $fromDb;
        }

        return ['Run IcctOffenseSeeder to load the Code of Offenses catalog.'];
    }

    public function syncOffenseNatureDropdowns(): void
    {
        $choices = $this->offenseNatureChoices();

        if ($choices === [] || $choices === ['Run IcctOffenseSeeder to load the Code of Offenses catalog.']) {
            return;
        }

        $optionsJson = [
            'choices' => array_map(fn (string $choice) => [
                'label' => $choice,
                'value' => $this->choiceValue($choice),
            ], $choices),
        ];

        $formIds = CompanyDocumentForm::query()
            ->whereIn('code', self::OFFENSE_BLOCK_FORM_CODES)
            ->pluck('company_document_form_id');

        if ($formIds->isEmpty()) {
            return;
        }

        CompanyDocumentElement::query()
            ->whereIn('company_document_form_id', $formIds)
            ->where('field_key', 'nature_of_offense')
            ->update([
                'type' => CompanyDocumentElement::TYPE_DROPDOWN,
                'label' => 'Nature of offense',
                'help_text' => 'Select from the ICCT Code of Offenses (effective February 16, 2011).',
                'is_required' => true,
                'options_json' => $optionsJson,
            ]);
    }

    /**
     * @return list<string>
     */
    private function frequencyChoices(): array
    {
        return [
            'First Offense',
            'Second Offense',
            'Third Offense',
            'Fourth Offense',
            'Fifth Offense',
            'Sixth Offense',
        ];
    }

    /**
     * @return list<string>
     */
    private function penaltyChoices(): array
    {
        $fromDb = LuIcctOffensePenalty::distinctPenalties();

        if ($fromDb !== []) {
            return $fromDb;
        }

        return [
            'Verbal Reprimand (but recorded for reference)',
            'Written Warning',
            '2 Working Days Suspension',
            '3 Working Days Suspension',
            '4 Working Days Suspension',
            '5 Working Days Suspension',
            '7 Working Days Suspension',
            'Dismissal',
        ];
    }

    private function penaltyTableText(): string
    {
        $rows = LuIcctOffensePenalty::matrixOrdered();

        if ($rows->isEmpty()) {
            return "Table of Penalties (effective February 16, 2011)\n"
                ."Category A: 1st Verbal Reprimand (recorded); 2nd Written Warning; 3rd 2 WD Suspension; 4th 4 WD Suspension; 5th 7 WD Suspension; 6th Dismissal.\n"
                ."Category B: 1st Written Warning; 2nd 3 WD Suspension; 3rd 5 WD Suspension; 4th Dismissal.\n"
                ."Category C: 1st 5 WD Suspension; 2nd Dismissal.\n"
                .'Category D: 1st Dismissal.';
        }

        $lines = ['Table of Penalties (effective February 16, 2011)'];

        foreach (['A', 'B', 'C', 'D'] as $category) {
            $categoryRows = $rows->where('category', $category)->values();

            if ($categoryRows->isEmpty()) {
                continue;
            }

            $parts = $categoryRows
                ->map(fn (LuIcctOffensePenalty $row) => $row->frequency_ordinal.LuIcctOffensePenalty::ordinalSuffix($row->frequency_ordinal).' '.$row->penalty)
                ->all();

            $lines[] = 'Category '.$category.': '.implode('; ', $parts).'.';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function heading(string $label): array
    {
        return ['type' => CompanyDocumentElement::TYPE_HEADING, 'label' => $label];
    }

    /**
     * @return array<string, mixed>
     */
    private function paragraph(string $label): array
    {
        return ['type' => CompanyDocumentElement::TYPE_PARAGRAPH, 'label' => $label];
    }

    /**
     * @return array<string, mixed>
     */
    private function shortText(string $label, string $fieldKey, bool $required, ?string $help = null): array
    {
        return $this->input(CompanyDocumentElement::TYPE_SHORT_TEXT, $label, $fieldKey, $required, $help);
    }

    /**
     * @return array<string, mixed>
     */
    private function longText(string $label, string $fieldKey, bool $required, ?string $help = null): array
    {
        return $this->input(CompanyDocumentElement::TYPE_LONG_TEXT, $label, $fieldKey, $required, $help);
    }

    /**
     * @return array<string, mixed>
     */
    private function number(string $label, string $fieldKey, bool $required): array
    {
        return $this->input(CompanyDocumentElement::TYPE_NUMBER, $label, $fieldKey, $required);
    }

    /**
     * @return array<string, mixed>
     */
    private function date(string $label, string $fieldKey, bool $required): array
    {
        return $this->input(CompanyDocumentElement::TYPE_DATE, $label, $fieldKey, $required);
    }

    /**
     * @return array<string, mixed>
     */
    private function yesNo(string $label, string $fieldKey, bool $required): array
    {
        return $this->input(CompanyDocumentElement::TYPE_YES_NO, $label, $fieldKey, $required);
    }

    /**
     * @return array<string, mixed>
     */
    private function signature(string $label, string $fieldKey): array
    {
        return $this->input(CompanyDocumentElement::TYPE_SIGNATURE, $label, $fieldKey, true);
    }

    /**
     * @param  list<string>  $choices
     * @return array<string, mixed>
     */
    private function dropdown(string $label, string $fieldKey, array $choices, bool $required): array
    {
        return array_merge($this->input(CompanyDocumentElement::TYPE_DROPDOWN, $label, $fieldKey, $required), [
            'options_json' => [
                'choices' => array_map(fn (string $choice) => [
                    'label' => $choice,
                    'value' => $this->choiceValue($choice),
                ], $choices),
            ],
        ]);
    }

    /**
     * @param  list<array{label: string, value: string}>  $choices
     * @return array<string, mixed>
     */
    private function radio(string $label, string $fieldKey, array $choices, bool $required): array
    {
        return array_merge($this->input(CompanyDocumentElement::TYPE_RADIO, $label, $fieldKey, $required), [
            'options_json' => ['choices' => $choices],
        ]);
    }

    /**
     * @param  list<array{label: string, value: string}>  $choices
     * @return array<string, mixed>
     */
    private function checkbox(string $label, string $fieldKey, array $choices, bool $required): array
    {
        return array_merge($this->input(CompanyDocumentElement::TYPE_CHECKBOX, $label, $fieldKey, $required), [
            'options_json' => ['choices' => $choices],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeTag(string $tagKey): array
    {
        return [
            'type' => CompanyDocumentElement::TYPE_MERGE_TAG,
            'label' => $tagKey,
            'settings_json' => [
                'label_align' => 'top',
                'tag_key' => $tagKey,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function input(string $type, string $label, string $fieldKey, bool $required, ?string $help = null): array
    {
        $element = [
            'type' => $type,
            'label' => $label,
            'field_key' => $fieldKey,
            'is_required' => $required,
        ];

        if ($help !== null) {
            $element['help_text'] = $help;
        }

        return $element;
    }

    private function choiceValue(string $label): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $label) ?? ''));

        return trim($slug, '_') ?: 'option';
    }
}
