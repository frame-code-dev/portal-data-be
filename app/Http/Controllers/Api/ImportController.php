<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportExcelRequest;
use App\Services\ExcelImportService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        private readonly ExcelImportService $importService
    ) {}

    public function import(ImportExcelRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $result = $this->importService->import($file);

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function importDefaultFiles(): JsonResponse
    {
        $dir = '/home/whoami/Downloads/excel';
        if (!is_dir($dir)) {
            // Check if copied inside project
            $dir = base_path('excel_sources');
        }

        if (!is_dir($dir)) {
            return response()->json([
                'success' => false,
                'message' => "Direktori file excel tidak ditemukan: {$dir}",
            ], 404);
        }

        $files = glob($dir . '/*.xlsx');
        if (empty($files)) {
            return response()->json([
                'success' => false,
                'message' => "Tidak ada file .xlsx di {$dir}",
            ], 404);
        }

        $results = [];
        $totalImported = 0;

        foreach ($files as $file) {
            try {
                $res = $this->importService->import($file);
                $results[] = [
                    'file' => basename($file),
                    'status' => 'success',
                    'count' => $res['imported_count'],
                ];
                $totalImported += $res['imported_count'];
            } catch (Exception $e) {
                $results[] = [
                    'file' => basename($file),
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengimpor total {$totalImported} data dari " . count($files) . " file.",
            'details' => $results,
        ]);
    }
}
