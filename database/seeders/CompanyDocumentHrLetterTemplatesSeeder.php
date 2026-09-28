<?php

namespace Database\Seeders;

use App\Models\CompanyDocumentApproval;
use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Replaces legacy Company Documents memo templates with HR letter/memo layouts
 * sourced from ICCT Word templates (Letters 1–5, attendance/property memos).
 * Idempotent by form code; retires any other active templates.
 */
class CompanyDocumentHrLetterTemplatesSeeder extends Seeder
{
    /** @var list<string> */
    public const TEMPLATE_CODES = [
        'hr_notice_to_explain',
        'hr_letter_nte_followup_2',
        'hr_letter_nte_followup_3',
        'hr_letter_admin_investigation',
        'hr_letter_dismissal_notice',
        'hr_internal_memo',
        'hr_memo_absences_tardiness',
        'hr_memo_company_property',
        'hr_memo_philhealth_compliance',
    ];

    public function run(): void
    {
        $this->retireLegacyTemplates();

        $sort = 10;
        foreach ($this->templateDefinitions() as $definition) {
            $definition['sort_order'] = $sort++;
            $this->seedTemplate($definition);
        }
    }

    private function retireLegacyTemplates(): void
    {
        CompanyDocumentForm::query()
            ->whereNotIn('code', self::TEMPLATE_CODES)
            ->each(function (CompanyDocumentForm $form): void {
                $this->clearApprovals($form);
                $form->elements()->each(fn (CompanyDocumentElement $element) => $element->forceDelete());
                if (! $form->trashed()) {
                    $form->delete();
                }
            });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templateDefinitions(): array
    {
        return [
            $this->letterAwolContinuedAbsences(),
            $this->letterNteFollowup2(),
            $this->letterNteFollowup3(),
            $this->letterAdminInvestigation(),
            $this->letterDismissalNotice(),
            $this->memoAttendancePattern(),
            $this->memoAbsencesTardiness(),
            $this->memoCompanyProperty(),
            $this->memoPhilhealthCompliance(),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedTemplate(array $definition): void
    {
        $elements = $definition['elements'];
        unset($definition['elements']);

        /** @var CompanyDocumentForm $form */
        $form = CompanyDocumentForm::withTrashed()->updateOrCreate(
            ['code' => $definition['code']],
            array_merge($definition, [
                'document_type' => CompanyDocumentForm::TYPE_MEMO,
                'allow_multiple_submissions' => true,
                'is_active' => true,
                'version' => 1,
            ]),
        );

        if ($form->trashed()) {
            $form->restore();
        }

        $this->clearApprovals($form);
        $form->elements()->each(fn (CompanyDocumentElement $element) => $element->forceDelete());

        $logoPath = $this->ensureLogoAsset($form);
        $y = 24;
        $allElements = array_merge([$this->logoElement($logoPath, $y)], $elements);
        $y = 136;

        foreach ($allElements as $index => $element) {
            if ($index > 0) {
                $element = $this->positionElement($element, $y);
                $y += $this->elementHeight($element) + 12;
            }

            CompanyDocumentElement::query()->create(array_merge([
                'company_document_form_id' => $form->company_document_form_id,
                'width' => $element['width'] ?? 'full',
            ], $element));
        }
    }

    private function ensureLogoAsset(CompanyDocumentForm $form): string
    {
        $directory = 'company-documents/templates/'.$form->company_document_form_id;
        $path = $directory.'/icct-colleges-logo.png';
        $source = resource_path('seeders/assets/icct-colleges-logo.png');
        if (! is_file($source)) {
            $source = public_path('img/icct-colleges-logo.png');
        }

        if (! is_file($source)) {
            throw new \RuntimeException('Missing ICCT logo PNG for company document templates.');
        }

        Storage::disk('local')->makeDirectory($directory);
        Storage::disk('local')->put($path, File::get($source));

        $legacyJpeg = $directory.'/icct-colleges-logo.jpg';
        if (Storage::disk('local')->exists($legacyJpeg)) {
            Storage::disk('local')->delete($legacyJpeg);
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function logoElement(string $path, int $y): array
    {
        return [
            'type' => 'image',
            'label' => 'ICCT Logo',
            'width' => 'full',
            'options_json' => [
                'path' => $path,
                'original_filename' => 'icct-colleges-logo.png',
            ],
            'settings_json' => [
                'label_align' => 'top',
                'pos_x' => 240,
                'pos_y' => $y,
                'image_width' => 160,
                'image_height' => 160,
                'image_opacity' => 100,
                'image_rotate' => 0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array<string, mixed>
     */
    private function positionElement(array $element, int $y): array
    {
        $settings = is_array($element['settings_json'] ?? null) ? $element['settings_json'] : [];
        $settings['label_align'] = $settings['label_align'] ?? 'top';
        $settings['pos_x'] = $settings['pos_x'] ?? 48;
        $settings['pos_y'] = $y;
        $element['settings_json'] = $settings;

        return $element;
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function elementHeight(array $element): int
    {
        return match ($element['type'] ?? '') {
            CompanyDocumentElement::TYPE_HEADING => 32,
            CompanyDocumentElement::TYPE_SHORT_TEXT,
            CompanyDocumentElement::TYPE_DATE => 44,
            CompanyDocumentElement::TYPE_SIGNATURE => 120,
            CompanyDocumentElement::TYPE_MERGE_TAG => 28,
            default => max(48, min(420, (int) (strlen((string) ($element['label'] ?? '')) / 72) * 18 + 40)),
        };
    }

    private function clearApprovals(CompanyDocumentForm $form): void
    {
        CompanyDocumentApproval::query()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->each(function (CompanyDocumentApproval $approval): void {
                $approval->assignees()->forceDelete();
                $approval->forceDelete();
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function letterAwolContinuedAbsences(): array
    {
        return [
            'code' => 'hr_notice_to_explain',
            'name' => 'Letter — Continued Absences / AWOL (NTE)',
            'description' => 'Request for written explanation for continued absences and AWOL (Letter 1). Used as the active Notice to Explain (NTE) template.',
            'submit_label' => 'Issue Letter',
            'success_message' => 'Letter has been recorded.',
            'is_nte' => true,
            'requires_nte' => false,
            'elements' => [
                $this->heading('ICCT COLLEGES FOUNDATION, INC.'),
                $this->paragraph('V.V Soliven Avenue II, Cainta, Rizal'),
                $this->shortText('Control No.', 'control_no', true),
                $this->shortText('Delivery', 'delivery_via', false, 'e.g. Via LBC, Via Registered Mail'),
                $this->date('Date', 'letter_date', true),
                $this->paragraph("Mr. / Ms. {{employee_full_name}}\nResidential Address"),
                $this->paragraph(
                    "Dear Mr. / Ms. {{employee_full_name}},\n\n"
                    ."We note of your continued absences since __________.\n\n"
                    ."Please be advised that this is a failure to provide the 30-days advance notice, as per Section 5.3 of the Employment Contract. "
                    ."Your unprofessional acts and omissions not only prejudiced the students in the subjects you are handling but also caused unquantifiable damage to ICCT Colleges, as well as administrative difficulties.\n\n"
                    ."There was also no justification or proof of any mitigating circumstance that would explain your absences.\n\n"
                    ."Please be reminded that your continued absences is a violation under the Code of Offenses & Table of Penalties, Table II. Offenses Against Administration:\n\n"
                    ."Section 9 — Absence without official leave (AWOL) for five (5) consecutive days or more. Category D (Dismissal).\n\n"
                    ."Therefore, please submit a written explanation within five (5) days from receipt hereof why no disciplinary action should be meted on you including dismissal if warranted for violating the above-stated violation. "
                    ."Failure to submit your explanation within the said period shall be construed as a waiver on your part to provide the same. "
                    ."Thereafter, the charges against you shall be decided based on the records and available evidences at hand.\n\n"
                    ."Yours sincerely,\n\n\nAu G. Esma\nVice-President, Administration\n\n"
                    ."Cc:\tDepartment Head\n\tJulie L. Ang — Vice-President, Accounting\n\t201 File",
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letterNteFollowup2(): array
    {
        return [
            'code' => 'hr_letter_nte_followup_2',
            'name' => 'Letter — 2nd Request for Written Explanation',
            'description' => 'Second request to submit a written explanation (Letter 2).',
            'submit_label' => 'Issue Letter',
            'success_message' => 'Letter has been recorded.',
            'elements' => $this->followupLetterElements(
                'We note your failure to respond to our letter dated _____ (Control No. __________).',
                'This is our 2nd Request for you to submit a written explanation. Therefore, we will expect your clarification until __________ (1 week later) before we proceed with any action.',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letterNteFollowup3(): array
    {
        return [
            'code' => 'hr_letter_nte_followup_3',
            'name' => 'Letter — 3rd Request for Written Explanation',
            'description' => 'Third request to submit a written explanation (Letter 3).',
            'submit_label' => 'Issue Letter',
            'success_message' => 'Letter has been recorded.',
            'elements' => $this->followupLetterElements(
                'We note your continued failure to respond to our letters dated _____ (Control No. __________).',
                'This is our 3rd Request for you to submit a written explanation. Therefore, we will expect your clarification until __________ (1 week later) before we proceed with an Administrative Investigation.',
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function followupLetterElements(string $opening, string $requestBody): array
    {
        return [
            $this->heading('ICCT COLLEGES FOUNDATION, INC.'),
            $this->paragraph('V.V Soliven Avenue II, Cainta, Rizal'),
            $this->shortText('Control No.', 'control_no', true),
            $this->shortText('Delivery', 'delivery_via', false, 'Via Registered Mail'),
            $this->date('Date', 'letter_date', true),
            $this->paragraph("Mr. / Ms. {{employee_full_name}}\nResidential Address"),
            $this->paragraph(
                "Dear Mr. / Ms. {{employee_full_name}},\n\n"
                .$opening."\n\n"
                .$requestBody."\n\n"
                ."Yours sincerely,\n\n\nAu G. Esma\nVice-President, Administration\n\n"
                ."Cc:\tDepartment Head\n\tJulie L. Ang — Vice-President, Accounting\n\t201 File",
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letterAdminInvestigation(): array
    {
        return [
            'code' => 'hr_letter_admin_investigation',
            'name' => 'Letter — Administrative Investigation',
            'description' => 'Notice to proceed to administrative investigation (Letter 4).',
            'submit_label' => 'Issue Letter',
            'success_message' => 'Letter has been recorded.',
            'elements' => [
                $this->heading('ICCT COLLEGES FOUNDATION, INC.'),
                $this->paragraph('V.V Soliven Avenue II, Cainta, Rizal'),
                $this->shortText('Control No.', 'control_no', true),
                $this->shortText('Delivery', 'delivery_via', false),
                $this->date('Date', 'letter_date', true),
                $this->paragraph("Mr. / Ms. {{employee_full_name}}\nResidential Address"),
                $this->paragraph(
                    "Dear Mr. / Ms. {{employee_full_name}},\n\n"
                    ."Further to your failure to respond to our 3rd Request letter dated _____ (Control No. __________), please note that we will now proceed to an Administrative Investigation. "
                    ."The meeting is scheduled on __________, 10 am at Cainta main campus.\n\n"
                    ."You may bring along a representative or counsel of your choice in the said investigation. "
                    ."Failure to appear on the said investigation shall be considered as waiver on your part to air your side, and the case shall be decided based on the available records.\n\n"
                    ."For your compliance.\n\n\nAu G. Esma\nVice-President, Administration\n\n"
                    ."Cc:\tDepartment Head\n\tJulie L. Ang — Vice-President, Accounting\n\t201 File",
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letterDismissalNotice(): array
    {
        return [
            'code' => 'hr_letter_dismissal_notice',
            'name' => 'Letter — Dismissal Notice',
            'description' => 'Dismissal notice after administrative hearing (Letter 5).',
            'submit_label' => 'Issue Letter',
            'success_message' => 'Letter has been recorded.',
            'elements' => [
                $this->heading('ICCT COLLEGES FOUNDATION, INC.'),
                $this->paragraph('V.V Soliven Avenue II, Cainta, Rizal'),
                $this->shortText('Control No.', 'control_no', true),
                $this->shortText('Delivery', 'delivery_via', false),
                $this->date('Date', 'letter_date', true),
                $this->paragraph("Mr. / Ms. {{employee_full_name}}\nResidential Address"),
                $this->longText('Facts cited', 'dismissal_facts', true, 'List the facts supporting the finding.'),
                $this->longText('Offense provision', 'offense_provision', true),
                $this->longText('Penalty imposed', 'penalty_imposed', true),
                $this->paragraph(
                    "Dear Mr. / Ms. {{employee_full_name}},\n\n"
                    ."Further to your failure to attend the Administrative Hearing scheduled on ______ in connection with the cited offense, as well as the absence of any new/additional material from you, ICCT Colleges has found you in violation of the Code of Offenses & Table of Penalties.\n\n"
                    ."In light of the above, ICCT Colleges has found you liable and deems it right to dismiss your employment.\n\n\nAu G. Esma\nVice-President, Administration\n\ncc:\tDepartment Head",
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memoAttendancePattern(): array
    {
        return [
            'code' => 'hr_internal_memo',
            'name' => 'Internal Memo — Attendance Pattern',
            'description' => 'Memorandum directing correction of repeated tardiness and absences (sample: attendance pattern memo).',
            'submit_label' => 'Issue Memo',
            'success_message' => 'Memo has been recorded.',
            'elements' => $this->memoHeaderElements(
                'Attendances',
                "Based on official records, you demonstrated a pattern of repeated tardiness and absences. This behavior is unacceptable and has resulted in operational disruptions, scheduling challenges, and increased workload for other staff members.\n\n"
                ."Effective immediately, you are expected to correct your attendance and fully comply with your assigned work schedule. Specifically, you are directed to adhere to the following requirements:\n\n"
                ."• Report to work on time and remain on duty for your entire scheduled shift.\n"
                ."• Unexcused absences will not be tolerated. All absences must be properly requested, approved in advance, and supported by required documentation when applicable.\n"
                ."• Any additional instances of tardiness, unapproved absences, or failure to follow established attendance and reporting procedures may result in further disciplinary action, up to and including a final written warning, suspension, or termination, in accordance with company policy and due process.\n\n"
                ."You are required to meet with the Human Resources Department within five (5) working days of receipt of this memorandum to review expectations, corrective measures, and monitoring procedures.\n\n"
                ."For your compliance.",
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memoAbsencesTardiness(): array
    {
        return [
            'code' => 'hr_memo_absences_tardiness',
            'name' => 'Internal Memo — Absences & Tardiness',
            'description' => 'Memorandum for continued absences and tardiness with Code of Offenses reference.',
            'submit_label' => 'Issue Memo',
            'success_message' => 'Memo has been recorded.',
            'elements' => array_merge(
                $this->memoHeaderElements(
                    'Attendances',
                    "We have noted your continued absences and tardiness as shown on the attached summary of attendance.\n\n"
                    ."Please be reminded that as per the Code of Offenses & Table of Penalties, Table II. Offenses Against Administration:\n\n"
                    ."Section 8 — Whole day absence without official leave (AWOL). Category B (Written Warning).\n"
                    ."Section 23 — Habitual tardiness, committing five (5) times late in a month. Category A (Verbal Reprimand — but recorded for reference).\n\n"
                    ."Disciplinary action: {{disciplinary_action}}\nOffense frequency: {{offense_frequency}}\n\n"
                    ."Please submit a written explanation regarding the issue within forty-eight (48) hours upon receipt of this memorandum.\n\n"
                    ."We look forward to your cooperation.",
                ),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memoCompanyProperty(): array
    {
        return [
            'code' => 'hr_memo_company_property',
            'name' => 'Internal Memo — Company Property',
            'description' => 'Memorandum regarding release of company property without proper documents.',
            'submit_label' => 'Issue Memo',
            'success_message' => 'Memo has been recorded.',
            'elements' => $this->memoHeaderElements(
                'Release of Company Property',
                "It has been noted that company property was released or taken out without the required transmittal or authority documents.\n\n"
                ."Please be reminded that as per the Code of Offenses & Table of Penalties, Table II. Offenses Against Company Interest and Policy:\n\n"
                ."Section 19 — Releasing or taking out from any place, warehouse or storage or delivering more than what is authorized in the invoice, delivery receipt, gate pass or authority. This includes non-submission of relevant documents. Category C–D (5 Working Days Suspension or Dismissal).\n\n"
                ."Please submit a written explanation regarding the issue within forty-eight (48) hours upon receipt of this memorandum.\n\n"
                ."We look forward to your cooperation.",
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memoPhilhealthCompliance(): array
    {
        return [
            'code' => 'hr_memo_philhealth_compliance',
            'name' => 'Internal Memo — PhilHealth Compliance',
            'description' => 'Memorandum requiring submission of PhilHealth number for government remittance.',
            'submit_label' => 'Issue Memo',
            'success_message' => 'Memo has been recorded.',
            'elements' => array_merge(
                $this->memoHeaderElements(
                    'Philhealth Number',
                    "It has been noted that you started your employment with us on __________. As part of our pre-employment requirements, you were advised to submit your complete and correct PhilHealth Number. "
                    ."This is necessary as we are currently preparing our monthly government remittance.\n\n"
                    ."Due to the pending submission, the remittance of contributions for all ICCT employees is currently on hold. In this regard, please be advised that __________ is the deadline for your compliance.\n\n"
                    ."Failure to do so will compel us to hold your salary, until such time when you have complied.\n\n"
                    ."I confirm understanding and agreement to the above statement.\n\n"
                    ."_________________________\t\t______________\nSignature above Printed Name\t\t\tDate",
                ),
                [
                    $this->signature('Employee acknowledgment', 'employee_acknowledgment'),
                ],
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function memoHeaderElements(string $subject, string $body): array
    {
        return [
            $this->heading('ICCT COLLEGES FOUNDATION, INC.'),
            $this->paragraph('V.V Soliven Avenue II, Cainta, Rizal'),
            $this->heading('M E M O R A N D U M'),
            $this->shortText('Control No.', 'control_no', true),
            $this->paragraph('Confidential'),
            $this->shortText('From', 'memo_from', true, 'e.g. Aurora G. Esma — Vice-President, HR & Administration'),
            $this->paragraph("To:\t{{employee_full_name}}"),
            $this->date('Date', 'memo_date', true),
            $this->shortText('Subject', 'subject', true, $subject),
            $this->longText('Cc', 'memo_cc', false),
            $this->paragraph($body."\n\n\nAurora G. Esma\nVice-President, HR & Administration"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function heading(string $label): array
    {
        return [
            'type' => CompanyDocumentElement::TYPE_HEADING,
            'label' => $label,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 48],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paragraph(string $label): array
    {
        return [
            'type' => CompanyDocumentElement::TYPE_PARAGRAPH,
            'label' => $label,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 48],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shortText(string $label, string $fieldKey, bool $required, ?string $help = null): array
    {
        return [
            'type' => CompanyDocumentElement::TYPE_SHORT_TEXT,
            'label' => $label,
            'field_key' => $fieldKey,
            'is_required' => $required,
            'help_text' => $help,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 48],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function longText(string $label, string $fieldKey, bool $required, ?string $help = null): array
    {
        return [
            'type' => CompanyDocumentElement::TYPE_LONG_TEXT,
            'label' => $label,
            'field_key' => $fieldKey,
            'is_required' => $required,
            'help_text' => $help,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 48],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function date(string $label, string $fieldKey, bool $required): array
    {
        return [
            'type' => CompanyDocumentElement::TYPE_DATE,
            'label' => $label,
            'field_key' => $fieldKey,
            'is_required' => $required,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 48],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function signature(string $label, string $fieldKey): array
    {
        return [
            'type' => CompanyDocumentElement::TYPE_SIGNATURE,
            'label' => $label,
            'field_key' => $fieldKey,
            'is_required' => false,
            'settings_json' => ['label_align' => 'top', 'pos_x' => 48],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeTag(string $label, string $tagKey): array
    {
        return [
            'type' => CompanyDocumentElement::TYPE_MERGE_TAG,
            'label' => $label,
            'settings_json' => [
                'label_align' => 'top',
                'pos_x' => 48,
                'tag_key' => $tagKey,
            ],
        ];
    }
}
