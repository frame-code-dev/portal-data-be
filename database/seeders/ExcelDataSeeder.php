<?php

namespace Database\Seeders;

use App\Services\ExcelImportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ExcelDataSeeder extends Seeder
{
    public function __construct(
        private readonly ExcelImportService $importService
    ) {}

    public function run(): void
    {
        $dir = base_path('excel_sources');
        if (!is_dir($dir)) {
            $dir = '/home/whoami/Downloads/excel';
        }

        if (!is_dir($dir)) {
            $this->command?->warn("Direktori excel tidak ditemukan di {$dir}");
            return;
        }

        $files = glob($dir . '/*.xlsx');
        sort($files);

        foreach ($files as $file) {
            try {
                $res = $this->importService->import($file);
                $this->command?->info("Imported: " . basename($file) . " (" . $res['imported_count'] . " items)");
            } catch (\Exception $e) {
                $this->command?->error("Failed to import " . basename($file) . ": " . $e->getMessage());
            }
        }
    }
}
