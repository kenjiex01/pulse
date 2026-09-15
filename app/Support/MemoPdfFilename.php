<?php

namespace App\Support;

use App\Models\CompanyDocumentForm;
use App\Models\Employee;
use App\Models\TimekeepingMemoSetup;
use Illuminate\Support\Str;

class MemoPdfFilename
{
    public static function for(
        CompanyDocumentForm $form,
        Employee $employee,
        string $violationType,
    ): string {
        $formPart = Str::slug($form->name ?: $form->code ?: 'memo');
        $employeePart = Str::slug((string) ($employee->employee_number ?: $employee->full_name ?: 'employee'));
        $typePart = Str::slug(TimekeepingMemoSetup::labelForType($violationType));

        return trim($formPart.'-'.$typePart.'-'.$employeePart, '-').'.pdf';
    }

    public static function docxFor(
        CompanyDocumentForm $form,
        Employee $employee,
        string $violationType,
    ): string {
        return (string) preg_replace('/\.pdf$/i', '.docx', self::for($form, $employee, $violationType));
    }
}
