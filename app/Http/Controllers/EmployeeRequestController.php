<?php

namespace App\Http\Controllers;

use App\Models\EmployeeRequest;
use App\Services\EmployeeRequestPullService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class EmployeeRequestController extends Controller
{
    public function __construct(
        private readonly EmployeeRequestPullService $pulls,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));

        $requests = EmployeeRequest::query()
            ->select([
                'employee_request_id',
                'request_number',
                'request_type_code',
                'request_type_name',
                'prepared_by_employee_number',
                'prepared_by_name',
                'department',
                'campus_name',
                'approved_at',
                'apply_summary',
                'employee_id',
                'form_payload',
                'items',
                'approvers',
            ])
            ->with([
                'employee:employee_id,employee_number,first_name,middle_name,last_name',
                'payrollInclusions' => fn ($query) => $query->whereNotNull('included_at')->with('payrollBatch:payroll_batch_id,batch_no'),
            ])
            ->when($type !== '', fn ($query) => $query->where('request_type_code', $type))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('request_number', 'like', "%{$search}%")
                        ->orWhere('prepared_by_name', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('campus_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('approved_at')
            ->orderByDesc('employee_request_id')
            ->paginate(20)
            ->withQueryString();

        $types = EmployeeRequest::query()
            ->select('request_type_code', 'request_type_name')
            ->distinct()
            ->orderBy('request_type_name')
            ->get();

        return view('hr.employee-requests.index', [
            'requests' => $requests,
            'types' => $types,
            'search' => $search,
            'type' => $type,
        ]);
    }

    public function pull(Request $request): RedirectResponse
    {
        try {
            $summary = $this->pulls->pull($request->user());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('employee-requests.index')
                ->with('error', $exception->getMessage());
        }

        $message = $summary['pulled'] === 0
            ? 'No approved employee requests to pull.'
            : 'Pulled '.$summary['pulled'].' approved form'.($summary['pulled'] === 1 ? '' : 's')
                .'. '.$summary['created'].' new, '.$summary['updated'].' updated, '.$summary['applied'].' applied.';

        return redirect()
            ->route('employee-requests.index')
            ->with('success', $message);
    }
}
