<?php

namespace App\Http\Controllers;

use App\Services\LibreOfficeRuntimeInstaller;
use Illuminate\Http\JsonResponse;
use Throwable;

class DocumentPreviewEngineController extends Controller
{
    public function status(LibreOfficeRuntimeInstaller $installer): JsonResponse
    {
        return response()->json($installer->status());
    }

    public function install(LibreOfficeRuntimeInstaller $installer): JsonResponse
    {
        try {
            $binary = $installer->install();

            return response()->json([
                'ok' => true,
                'binary_path' => $binary,
                'status' => $installer->status(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
                'status' => $installer->status(),
            ], 422);
        }
    }
}
