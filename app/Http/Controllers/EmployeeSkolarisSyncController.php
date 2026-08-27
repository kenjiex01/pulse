<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\EmployeeSkolarisSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeSkolarisSyncController extends Controller
{
    public function __construct(private readonly EmployeeSkolarisSyncService $syncService) {}

    public function pending(Request $request): JsonResponse
    {
        $this->authorize('syncFromSkolaris', Employee::class);

        $result = $this->syncService->pendingSummaries($request->boolean('refresh'));

        return response()->json($result);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('syncFromSkolaris', Employee::class);

        $employeeNumber = trim((string) $request->query('employee_number', ''));
        if ($employeeNumber === '') {
            return response()->json(['ok' => false, 'message' => 'Employee number is required.'], 422);
        }

        $preview = $this->syncService->preview($employeeNumber);
        if ($preview === null) {
            return response()->json(['ok' => false, 'message' => 'No pending ISKOLARIS changes for this employee.'], 404);
        }

        return response()->json(['ok' => true, ...$preview]);
    }

    public function apply(Request $request): JsonResponse
    {
        $this->authorize('syncFromSkolaris', Employee::class);

        $validated = $request->validate([
            'employee_numbers' => ['required', 'array', 'min:1', 'max:200'],
            'employee_numbers.*' => ['required', 'string', 'max:50'],
        ]);

        $result = $this->syncService->apply($validated['employee_numbers']);

        return response()->json([
            'ok' => $result['failed'] === [],
            ...$result,
            'count' => $this->syncService->pendingSummaries()['count'] ?? 0,
            'message' => $this->resultMessage($result),
        ]);
    }

    /**
     * @param  array{applied: int, created: int, updated: int, failed: array<int, array{employee_number: string, message: string}>}  $result
     */
    private function resultMessage(array $result): string
    {
        $parts = [];
        if ($result['created'] > 0) {
            $parts[] = $result['created'].' created';
        }
        if ($result['updated'] > 0) {
            $parts[] = $result['updated'].' updated';
        }
        if ($result['failed'] !== []) {
            $parts[] = count($result['failed']).' failed';
        }

        if ($parts === []) {
            return 'No employees were approved.';
        }

        return 'ISKOLARIS approval finished: '.implode(', ', $parts).'.';
    }
}
