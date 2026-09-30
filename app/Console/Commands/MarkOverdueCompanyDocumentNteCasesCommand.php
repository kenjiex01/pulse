<?php

namespace App\Console\Commands;

use App\Services\CompanyDocumentNteCaseService;
use Illuminate\Console\Command;

class MarkOverdueCompanyDocumentNteCasesCommand extends Command
{
    protected $signature = 'company-documents:mark-overdue-nte-cases';

    protected $description = 'Mark pending company document NTE cases as overdue when past the response due date';

    public function handle(CompanyDocumentNteCaseService $service): int
    {
        $count = $service->markOverduePendingCases();

        $this->info('Marked '.$count.' NTE case(s) as overdue.');

        return self::SUCCESS;
    }
}
