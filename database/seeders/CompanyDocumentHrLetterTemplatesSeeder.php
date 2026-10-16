<?php

namespace Database\Seeders;

use App\Models\CompanyDocumentApproval;
use App\Models\CompanyDocumentElement;
use App\Models\CompanyDocumentForm;
use App\Models\TimekeepingMemoSetup;
use App\Support\CompanyDocumentMemoPdfLayout;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * ICCT letter and memo templates, laid out like Internal Memo — Absences & Tardiness.
 * Idempotent by form code; retires any other template.
 */
class CompanyDocumentHrLetterTemplatesSeeder extends Seeder
{
    /** Bump when installed databases must drop existing company documents and load these templates. */
    public const REVISION = 7;

    public const REVISION_MARKER = 'app/hr-letter-templates.revision';

    private const MARGIN_X = 56;

    private const LOGO_Y = 24;

    private const LOGO_SIZE = 128;

    private const ELEMENT_GAP = 12;

    private const MEMO_SIGN_OFF = "Aurora G. Esma\nVice-President, HR & Administration";

    private const LETTER_SIGN_OFF = "Au G. Esma\nVice-President, Administration";

    private const LETTER_CC = "Cc:\tDepartment Head\n\tJulie L. Ang\t-\tVice-President, Accounting\n\t201 File";

    /** @var list<string> */
    public const TEMPLATE_CODES = [
        'hr_memo_absences_tardiness',
        'hr_internal_memo',
        'hr_memo_company_property',
        'hr_memo_philhealth_compliance',
        'hr_notice_to_explain',
        'hr_letter_nte_followup_2',
        'hr_letter_nte_followup_3',
        'hr_letter_admin_investigation',
        'hr_letter_dismissal_notice',
    ];

    public function run(): void
    {
        $this->retireLegacyTemplates();

        $sort = 10;
        foreach ($this->templateDefinitions() as $definition) {
            $definition['sort_order'] = $sort++;
            $this->seedTemplate($definition);
        }

        File::ensureDirectoryExists(storage_path('app'));
        File::put(storage_path(self::REVISION_MARKER), (string) self::REVISION);
    }

    private function retireLegacyTemplates(): void
    {
        CompanyDocumentForm::query()
            ->where(function ($query): void {
                $query->whereNull('code')
                    ->orWhereNotIn('code', self::TEMPLATE_CODES);
            })
            ->each(function (CompanyDocumentForm $form): void {
                $this->clearApprovals($form);
                TimekeepingMemoSetup::query()
                    ->where('company_document_form_id', $form->company_document_form_id)
                    ->update(['company_document_form_id' => null]);
                $this->forceDeleteElements($form);
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
            $this->memoAbsencesTardiness(),
            $this->memoAttendancePattern(),
            $this->memoCompanyProperty(),
            $this->memoPhilhealthCompliance(),
            $this->letterContinuedAbsences(),
            $this->letterSecondRequest(),
            $this->letterThirdRequest(),
            $this->letterAdminInvestigation(),
            $this->letterDismissalNotice(),
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
                'icct_offense_id' => $definition['icct_offense_id'] ?? null,
                'requires_nte' => $definition['requires_nte'] ?? false,
                'expects_web_nte_response' => $definition['expects_web_nte_response'] ?? false,
                'nte_response_days' => $definition['nte_response_days'] ?? 7,
                'skolaris_nte_request_type_code' => null,
                'is_nte' => $definition['is_nte'] ?? false,
                'allow_multiple_submissions' => true,
                'is_active' => true,
                'settings_json' => $definition['settings_json'] ?? null,
                'version' => 1,
            ]),
        );

        if ($form->trashed()) {
            $form->restore();
        }

        $this->clearApprovals($form);
        $this->forceDeleteElements($form);

        $logoPath = $this->ensureLogoAsset($form);
        $logoElement = $this->logoElement($logoPath, self::LOGO_Y, self::LOGO_SIZE);
        $allElements = array_merge([$logoElement], $elements);
        $y = self::LOGO_Y + self::LOGO_SIZE + self::ELEMENT_GAP;

        foreach ($allElements as $index => $element) {
            if ($index > 0) {
                $element = $this->positionElement($element, $y);
                $y += $this->elementHeight($element) + self::ELEMENT_GAP;
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
        $candidates = [
            resource_path('seeders/assets/icct-colleges-logo.jpg'),
            resource_path('seeders/assets/icct-colleges-logo.png'),
            public_path('img/icct-colleges-logo.png'),
        ];

        $source = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $source = $candidate;
                break;
            }
        }

        if ($source === null) {
            throw new \RuntimeException('Missing ICCT logo asset for company document templates.');
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'png';
        $path = $directory.'/icct-colleges-logo.'.$extension;

        Storage::disk('local')->makeDirectory($directory);
        Storage::disk('local')->put($path, File::get($source));

        foreach (['png', 'jpg', 'jpeg'] as $legacyExt) {
            $legacyPath = $directory.'/icct-colleges-logo.'.$legacyExt;
            if ($legacyPath !== $path && Storage::disk('local')->exists($legacyPath)) {
                Storage::disk('local')->delete($legacyPath);
            }
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function logoElement(string $path, int $y, int $size): array
    {
        $filename = basename($path);
        $posX = $this->centeredImagePosX($size);

        return [
            'type' => 'image',
            'label' => 'ICCT Logo',
            'width' => 'full',
            'options_json' => [
                'path' => $path,
                'original_filename' => $filename,
            ],
            'settings_json' => [
                'label_align' => 'top',
                'pos_x' => $posX,
                'pos_y' => $y,
                'image_width' => $size,
                'image_height' => $size,
                'image_opacity' => 100,
                'image_rotate' => 0,
            ],
        ];
    }

    private function centeredImagePosX(int $imageWidth): int
    {
        $canvas = CompanyDocumentMemoPdfLayout::DESIGNER_REFERENCE_WIDTH;

        return max(0, (int) floor(($canvas - $imageWidth) / 2));
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array<string, mixed>
     */
    private function positionElement(array $element, int $y): array
    {
        $settings = is_array($element['settings_json'] ?? null) ? $element['settings_json'] : [];
        $settings['label_align'] = $settings['label_align'] ?? 'top';
        $settings['pos_x'] = $settings['pos_x'] ?? self::MARGIN_X;
        $settings['pos_y'] = $y;
        $element['settings_json'] = $settings;

        return $element;
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function elementHeight(array $element): int
    {
        $settings = is_array($element['settings_json'] ?? null) ? $element['settings_json'] : [];

        return match ($element['type'] ?? '') {
            CompanyDocumentElement::TYPE_HEADING => 32,
            CompanyDocumentElement::TYPE_SHORT_TEXT,
            CompanyDocumentElement::TYPE_DATE => 44,
            CompanyDocumentElement::TYPE_LONG_TEXT => CompanyDocumentMemoPdfLayout::FIELD_LONG_HEIGHT,
            CompanyDocumentElement::TYPE_SIGNATURE => 120,
            CompanyDocumentElement::TYPE_MERGE_TAG => 28,
            'image' => max(40, (int) ($settings['image_height'] ?? 128)),
            CompanyDocumentElement::TYPE_PARAGRAPH => max(
                48,
                min(720, (int) (strlen((string) ($element['label'] ?? '')) / 56) * 20 + 48),
            ),
            CompanyDocumentElement::TYPE_TABLE => 96,
            default => max(48, min(420, (int) (strlen((string) ($element['label'] ?? '')) / 72) * 18 + 40)),
        };
    }

    private function forceDeleteElements(CompanyDocumentForm $form): void
    {
        CompanyDocumentElement::withTrashed()
            ->where('company_document_form_id', $form->company_document_form_id)
            ->forceDelete();
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
    private function memoAbsencesTardiness(): array
    {
        $intro = "We have noted your continued absences and tardiness for the months of {{attendance_period}} as shown on the attached summary of attendance.\n\n"
            ."Please be reminded that as per the Code of Offenses & Table of Penalties, Table II. Offenses Against Administration:";
        $closing = "Please submit a written explanation regarding the issue within forty-eight (48) hours upon receipt of this memorandum.\n\n"
            ."We look forward to your cooperation.\n\n\n"
            ."Aurora G. Esma\n"
            ."Vice-President, HR & Administration";

        return [
            'code' => 'hr_memo_absences_tardiness',
            'name' => 'Internal Memo — Absences & Tardiness',
            'description' => 'Memorandum for continued absences and tardiness with Code of Offenses reference.',
            'submit_label' => 'Issue Memo',
            'success_message' => 'Memo has been recorded.',
            'is_nte' => false,
            'elements' => array_merge(
                $this->memoLetterheadElements(),
                [
                    $this->paragraph("Control No.\t{{control_no}}"),
                    $this->paragraph('<i>Confidential</i>'),
                    $this->paragraph("From:\t{{memo_from}}"),
                    $this->paragraph("To:\t{{employee_full_name}}\t-\t{{employee_designation}}"),
                    $this->paragraph("Date:\t{{memo_date}}"),
                    $this->paragraph("Subject:\t{{attendance_period}} Attendances"),
                    $this->paragraph("Cc:\t{{memo_cc}}"),
                    $this->paragraph($intro),
                    $this->table([
                        ['Nature of offense', 'Disciplinary action'],
                        ['{{nature_of_offense}}', '{{disciplinary_action}}'],
                    ]),
                    $this->paragraph($closing),
                ],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memoAttendancePattern(): array
    {
        $body = "Based on official records, you demonstrated a pattern of repeated tardiness and absences. This behavior is unacceptable and has resulted in operational disruptions, scheduling challenges, and increased workload for other staff members.\n\n"
            ."Effective immediately, you are expected to correct your attendance and fully comply with your assigned work schedule. Specifically, you are directed to adhere to the following requirements:\n\n"
            ."• Report to work on time and remain on duty for your entire scheduled shift.\n"
            ."• Unexcused absences will not be tolerated. All absences must be properly requested, approved in advance, and supported by required documentation when applicable. In the event of an emergency that prevents timely reporting, you must notify your immediate supervisor and the HR Department prior to the start of your shift (or as soon as reasonably possible) and submit appropriate documentation upon your return.\n"
            ."• Any additional instances of tardiness, unapproved absences, or failure to follow established attendance and reporting procedures may result in further disciplinary action, up to and including a final written warning, suspension, or termination, in accordance with company policy and due process.\n\n"
            ."You are required to meet with the Human Resources Department within five (5) working days of receipt of this memorandum to review expectations, corrective measures, and monitoring procedures.\n\n"
            ."For your compliance.\n\n\n"
            .self::MEMO_SIGN_OFF;

        return [
            'code' => 'hr_internal_memo',
            'name' => 'Internal Memo — Attendance Pattern',
            'description' => 'Memorandum directing correction of repeated tardiness and absences.',
            'submit_label' => 'Issue Memo',
            'success_message' => 'Memo has been recorded.',
            'is_nte' => false,
            'elements' => array_merge(
                $this->memoLetterheadElements(),
                $this->memoMetaElements('Attendances'),
                [$this->paragraph($body)],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memoCompanyProperty(): array
    {
        $intro = "It has been noted that company property was released or taken out without the required transmittal or authority documents.\n\n"
            .'Please be reminded that as per the Code of Offenses & Table of Penalties, Table II. Offenses Against Company Interest and Policy:';
        $closing = "Please submit a written explanation regarding the issue within forty-eight (48) hours upon receipt of this memorandum.\n\n"
            ."We look forward to your cooperation.\n\n\n"
            .self::MEMO_SIGN_OFF;

        return [
            'code' => 'hr_memo_company_property',
            'name' => 'Internal Memo — Company Property',
            'description' => 'Memorandum regarding release of company property without proper documents.',
            'submit_label' => 'Issue Memo',
            'success_message' => 'Memo has been recorded.',
            'is_nte' => false,
            'elements' => array_merge(
                $this->memoLetterheadElements(),
                $this->memoMetaElements('Release of Company Property'),
                [
                    $this->paragraph($intro),
                    $this->table([
                        ['Nature of offense', 'Disciplinary action'],
                        ['{{nature_of_offense}}', '{{disciplinary_action}}'],
                    ]),
                    $this->paragraph($closing),
                ],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memoPhilhealthCompliance(): array
    {
        $body = "It has been noted that you started your employment with us on {{hire_date}}. As part of our pre-employment requirements, you were advised to submit your complete and correct PhilHealth Number. This is necessary as we are currently preparing our monthly government remittance.\n\n"
            ."Due to the pending submission, the remittance of contributions for all ICCT employees is currently on hold. In this regard, please be advised that {{memo_date}} is the deadline for your compliance.\n\n"
            ."Failure to do so will compel us to hold your salary, until such time when you have complied.\n\n\n"
            .self::MEMO_SIGN_OFF."\n\n"
            ."I confirm understanding and agreement to the above statement.\n\n\n"
            ."_________________________\t\t______________\n"
            ."Signature above Printed Name\t\tDate";

        return [
            'code' => 'hr_memo_philhealth_compliance',
            'name' => 'Internal Memo — PhilHealth Number',
            'description' => 'Memorandum requiring submission of a PhilHealth number for government remittance.',
            'submit_label' => 'Issue Memo',
            'success_message' => 'Memo has been recorded.',
            'is_nte' => false,
            'elements' => array_merge(
                $this->memoLetterheadElements(),
                $this->memoMetaElements('PhilHealth Number'),
                [$this->paragraph($body)],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letterContinuedAbsences(): array
    {
        $intro = "Dear Mr. / Ms. {{employee_full_name}},\n\n"
            ."We note of your continued absences since __________.\n\n"
            ."Please be advised that this is a failure to provide the 30-days advance notice, as per Section 5.3 of the Employment Contract. Your unprofessional acts and omissions not only prejudiced the students in the subjects you are handling but also caused unquantifiable damage to ICCT Colleges, as well as administrative difficulties.\n\n"
            ."There was also no justification or proof of any mitigating circumstance that would explain your absences.\n\n"
            .'Please be reminded that your continued absences is a violation under the Code of Offenses & Table of Penalties, Table II. Offenses Against Administration:';
        $closing = "Therefore, please submit a written explanation within five (5) days from receipt hereof why no disciplinary action should be meted on you including dismissal if warranted for violating the above-stated violation. Failure to submit your explanation within the said period shall be construed as a waiver on your part to provide the same. Thereafter, the charges against you shall be decided based on the records and available evidences at hand.\n\n"
            ."Yours sincerely,\n\n\n"
            .self::LETTER_SIGN_OFF."\n\n"
            .self::LETTER_CC;

        return [
            'code' => 'hr_notice_to_explain',
            'name' => 'Letter 1 — Continued Absences / AWOL',
            'description' => 'Request for a written explanation for continued absences and AWOL. This is the Notice to Explain.',
            'submit_label' => 'Issue Letter',
            'success_message' => 'Letter has been recorded.',
            'is_nte' => true,
            'requires_nte' => false,
            'expects_web_nte_response' => true,
            'nte_response_days' => 5,
            'elements' => array_merge(
                $this->letterLetterheadElements(),
                $this->letterMetaElements('Via LBC'),
                [
                    $this->paragraph($intro),
                    $this->table([
                        ['Nature of offense', 'Disciplinary action'],
                        ['{{nature_of_offense}}', '{{disciplinary_action}}'],
                    ]),
                    $this->paragraph($closing),
                ],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letterSecondRequest(): array
    {
        $body = "Dear Mr. / Ms. {{employee_full_name}},\n\n"
            ."We note your failure to respond to our letter dated __________ (Control No. {{control_no}}).\n\n"
            ."This is our 2nd Request for you to submit a written explanation. Therefore, we will expect your clarification until __________ (1-week later) before we proceed with any action.\n\n"
            ."Yours sincerely,\n\n\n"
            .self::LETTER_SIGN_OFF."\n\n"
            .self::LETTER_CC;

        return [
            'code' => 'hr_letter_nte_followup_2',
            'name' => 'Letter 2 — Second Request for Explanation',
            'description' => 'Second request for a written explanation after no response to the first letter.',
            'submit_label' => 'Issue Letter',
            'success_message' => 'Letter has been recorded.',
            'is_nte' => false,
            'elements' => array_merge(
                $this->letterLetterheadElements(),
                $this->letterMetaElements('Via Registered Mail'),
                [$this->paragraph($body)],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letterThirdRequest(): array
    {
        $body = "Dear Mr. / Ms. {{employee_full_name}},\n\n"
            ."We note your continued failure to respond to our letters dated __________ (Control No. {{control_no}}).\n\n"
            ."This is our 3rd Request for you to submit a written explanation. Therefore, we will expect your clarification until __________ (1-week later) before we proceed with an Administrative Investigation.\n\n"
            ."Yours sincerely,\n\n\n"
            .self::LETTER_SIGN_OFF."\n\n"
            .self::LETTER_CC;

        return [
            'code' => 'hr_letter_nte_followup_3',
            'name' => 'Letter 3 — Third Request for Explanation',
            'description' => 'Third request for a written explanation before an administrative investigation.',
            'submit_label' => 'Issue Letter',
            'success_message' => 'Letter has been recorded.',
            'is_nte' => false,
            'elements' => array_merge(
                $this->letterLetterheadElements(),
                $this->letterMetaElements('Via Registered Mail'),
                [$this->paragraph($body)],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letterAdminInvestigation(): array
    {
        $body = "Dear Mr. / Ms. {{employee_full_name}},\n\n"
            ."Further to your failure to respond to our 3rd Request letter dated __________ (Control No. {{control_no}}), please note that we will now proceed to an Administrative Investigation. The meeting is scheduled on __________, 10 am at Cainta main campus.\n\n"
            ."You may bring along a representative or counsel of your choice in the said investigation. Failure to appear on the said investigation shall be considered as waiver on your part to air your side, and the case shall be decided based on the available records.\n\n"
            ."For your compliance.\n\n\n"
            .self::LETTER_SIGN_OFF."\n\n"
            .self::LETTER_CC;

        return [
            'code' => 'hr_letter_admin_investigation',
            'name' => 'Letter 4 — Administrative Investigation',
            'description' => 'Notice of the administrative investigation schedule after no response to the third request.',
            'submit_label' => 'Issue Letter',
            'success_message' => 'Letter has been recorded.',
            'is_nte' => false,
            'elements' => array_merge(
                $this->letterLetterheadElements(),
                $this->letterMetaElements('Via Registered Mail'),
                [$this->paragraph($body)],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letterDismissalNotice(): array
    {
        $intro = "Dear Mr. / Ms. {{employee_full_name}},\n\n"
            ."Further to your failure to attend the Administrative Hearing scheduled on __________ in connection with the cited offense, as well as the absence of any new/additional material from you, we note the following:\n\n"
            ."__________\n"
            ."__________\n"
            ."__________\n\n"
            .'Therefore, ICCT Colleges has found you in violation of the Code of Offenses & Table of Penalties:';
        $closing = "In light of the above, ICCT Colleges has found you liable and deems it right to dismiss your employment.\n\n\n"
            .self::LETTER_SIGN_OFF."\n\n"
            ."cc:\tDepartment Head";

        return [
            'code' => 'hr_letter_dismissal_notice',
            'name' => 'Letter 5 — Dismissal Notice',
            'description' => 'Notice of dismissal after failure to attend the administrative hearing.',
            'submit_label' => 'Issue Letter',
            'success_message' => 'Letter has been recorded.',
            'is_nte' => false,
            'elements' => array_merge(
                $this->letterLetterheadElements(),
                $this->letterMetaElements('Via Registered Mail'),
                [
                    $this->paragraph($intro),
                    $this->table([
                        ['Nature of offense', 'Disciplinary action'],
                        ['{{nature_of_offense}}', '{{disciplinary_action}}'],
                    ]),
                    $this->paragraph($closing),
                ],
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function memoMetaElements(string $subject): array
    {
        return [
            $this->paragraph("Control No.\t{{control_no}}"),
            $this->paragraph('<i>Confidential</i>'),
            $this->paragraph("From:\t{{memo_from}}"),
            $this->paragraph("To:\t{{employee_full_name}}\t-\t{{employee_designation}}"),
            $this->paragraph("Date:\t{{memo_date}}"),
            $this->paragraph("Subject:\t{$subject}"),
            $this->paragraph("Cc:\t{{memo_cc}}"),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function letterMetaElements(string $delivery): array
    {
        return [
            $this->paragraph("Control No.\t{{control_no}}"),
            $this->paragraph($delivery),
            $this->paragraph("Date:\t{{memo_date}}"),
            $this->paragraph("Mr. / Ms. {{employee_full_name}}\nResidential Address"),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function letterLetterheadElements(): array
    {
        return [
            $this->letterheadHeading('ICCT COLLEGES FOUNDATION, INC.'),
            $this->letterheadParagraph('V.V Soliven Avenue II, Cainta, Rizal'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function memoLetterheadElements(): array
    {
        return [
            $this->letterheadHeading('ICCT COLLEGES FOUNDATION, INC.'),
            $this->letterheadParagraph('V.V Soliven Avenue II, Cainta, Rizal'),
            $this->letterheadHeading('M E M O R A N D U M'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letterheadHeading(string $label): array
    {
        return [
            'type' => CompanyDocumentElement::TYPE_HEADING,
            'label' => $label,
            'settings_json' => [
                'label_align' => 'top',
                'pos_x' => 0,
                'text_align' => 'center',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function letterheadParagraph(string $label): array
    {
        return [
            'type' => CompanyDocumentElement::TYPE_PARAGRAPH,
            'label' => $label,
            'settings_json' => [
                'label_align' => 'top',
                'pos_x' => 0,
                'text_align' => 'center',
            ],
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array<string, mixed>
     */
    private function table(array $rows): array
    {
        return [
            'type' => CompanyDocumentElement::TYPE_TABLE,
            'label' => 'Table',
            'options_json' => ['rows' => $rows],
            'settings_json' => ['label_align' => 'top', 'pos_x' => self::MARGIN_X],
        ];
    }

    private function paragraph(string $label): array
    {
        return [
            'type' => CompanyDocumentElement::TYPE_PARAGRAPH,
            'label' => $label,
            'settings_json' => ['label_align' => 'top', 'pos_x' => self::MARGIN_X],
        ];
    }

}
