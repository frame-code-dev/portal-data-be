<?php

namespace App\Services;

use App\Models\Dataset;
use App\Repositories\DatasetRepository;
use App\Repositories\DatasetItemRepository;
use DOMDocument;
use DOMXPath;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class ExcelImportService
{
    public function __construct(
        private readonly DatasetRepository $datasetRepo,
        private readonly DatasetItemRepository $itemRepo,
    ) {}

    /**
     * Parse and import an Excel .xlsx file.
     *
     * @param string|UploadedFile $file
     * @return array
     * @throws Exception
     */
    public function import(string|UploadedFile $file): array
    {
        $filePath = is_string($file) ? $file : $file->getRealPath();

        if (!file_exists($filePath)) {
            throw new Exception("File tidak ditemukan: {$filePath}");
        }

        $xmlData = $this->extractXmlFiles($filePath);
        $sharedStrings = $this->parseSharedStrings($xmlData['sharedStrings'] ?? null);
        $rows = $this->parseSheetRows($xmlData['sheet'], $sharedStrings);

        if (empty($rows)) {
            throw new Exception("File excel tidak memiliki baris data yang valid.");
        }

        // Header is row 1
        $headerRow = $rows[1] ?? [];
        $colMap = [];
        foreach ($headerRow as $colLetter => $headerName) {
            $colMap[trim((string)$headerName)] = $colLetter;
        }

        // Verify required columns
        $requiredCols = ['dataset', 'judul', 'label_nilai', 'data__kode', 'data__nama', 'data__kategori', 'data__unit', 'data__tanggal', 'data__nilai', 'data__status'];
        foreach ($requiredCols as $rc) {
            if (!isset($colMap[$rc])) {
                throw new Exception("Format kolom excel tidak sesuai. Kolom '{$rc}' tidak ditemukan.");
            }
        }

        // Extract metadata from row 2 (first data row contains dataset info)
        $firstDataRow = $rows[2] ?? [];
        $datasetSlug = trim((string)($firstDataRow[$colMap['dataset']] ?? ''));
        $datasetTitle = trim((string)($firstDataRow[$colMap['judul']] ?? ''));
        $valueLabel = trim((string)($firstDataRow[$colMap['label_nilai']] ?? 'Nilai (rupiah)'));

        if (empty($datasetSlug)) {
            // fallback from filename if empty
            $basename = basename(is_string($file) ? $file : $file->getClientOriginalName(), '.xlsx');
            $datasetSlug = $basename;
            $datasetTitle = ucwords(str_replace(['-', '_'], ' ', $basename));
        }

        // Collect category and status options from column C and D or across all rows
        $categories = [];
        $statuses = [];

        foreach ($rows as $rIdx => $row) {
            if ($rIdx === 1) continue; // header

            if (isset($colMap['kategori']) && !empty($row[$colMap['kategori']])) {
                $categories[] = trim((string)$row[$colMap['kategori']]);
            }
            if (isset($colMap['status']) && !empty($row[$colMap['status']])) {
                $statuses[] = trim((string)$row[$colMap['status']]);
            }

            // Also collect from data__kategori and data__status to be exhaustive
            if (!empty($row[$colMap['data__kategori']])) {
                $categories[] = trim((string)$row[$colMap['data__kategori']]);
            }
            if (!empty($row[$colMap['data__status']])) {
                $statuses[] = trim((string)$row[$colMap['data__status']]);
            }
        }

        $categories = array_values(array_unique(array_filter($categories)));
        $statuses = array_values(array_unique(array_filter($statuses)));

        return DB::transaction(function () use ($datasetSlug, $datasetTitle, $valueLabel, $categories, $statuses, $rows, $colMap) {
            $dataset = $this->datasetRepo->createOrUpdate(
                ['slug' => $datasetSlug],
                [
                    'title' => $datasetTitle,
                    'value_label' => $valueLabel,
                    'categories' => $categories,
                    'statuses' => $statuses,
                ]
            );

            $importedCount = 0;
            foreach ($rows as $rIdx => $row) {
                if ($rIdx === 1) continue;

                $code = trim((string)($row[$colMap['data__kode']] ?? ''));
                $name = trim((string)($row[$colMap['data__nama']] ?? ''));

                if (empty($code) && empty($name)) {
                    continue;
                }

                $rawDate = trim((string)($row[$colMap['data__tanggal']] ?? ''));
                $formattedDate = $this->formatDate($rawDate);

                $rawValue = trim((string)($row[$colMap['data__nilai']] ?? '0'));
                $numericValue = (float)preg_replace('/[^0-9.]/', '', $rawValue);

                $category = trim((string)($row[$colMap['data__kategori']] ?? ''));
                $unit = trim((string)($row[$colMap['data__unit']] ?? ''));
                $status = trim((string)($row[$colMap['data__status']] ?? ''));
                $notes = isset($colMap['data__catatan']) ? trim((string)($row[$colMap['data__catatan']] ?? '')) : null;

                $this->itemRepo->upsertItem($dataset->id, [
                    'code' => $code ?: ('ITEM-' . $rIdx),
                    'name' => $name,
                    'category' => $category,
                    'unit' => $unit,
                    'date' => $formattedDate,
                    'value' => $numericValue,
                    'status' => $status,
                    'notes' => $notes ?: null,
                ]);

                $importedCount++;
            }

            return [
                'success' => true,
                'dataset' => $dataset->fresh()->loadCount('items'),
                'imported_count' => $importedCount,
                'message' => "Berhasil mengimpor {$importedCount} data untuk modul '{$dataset->title}'.",
            ];
        });
    }

    private function extractXmlFiles(string $filePath): array
    {
        $sharedStringsXml = null;
        $sheetXml = null;

        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $sContent = $zip->getFromName('xl/sharedStrings.xml');
                if ($sContent !== false) {
                    $sharedStringsXml = $sContent;
                }
                $shContent = $zip->getFromName('xl/worksheets/sheet1.xml');
                if ($shContent !== false) {
                    $sheetXml = $shContent;
                }
                $zip->close();
            }
        }

        // Fallback to unzip CLI if ZipArchive is absent or failed
        if ($sheetXml === null) {
            $escaped = escapeshellarg($filePath);
            $sharedStringsXml = shell_exec("unzip -p {$escaped} xl/sharedStrings.xml 2>/dev/null");
            $sheetXml = shell_exec("unzip -p {$escaped} xl/worksheets/sheet1.xml 2>/dev/null");
        }

        if (empty($sheetXml)) {
            throw new Exception("Tidak dapat membaca worksheet xml dari file excel.");
        }

        return [
            'sharedStrings' => $sharedStringsXml,
            'sheet' => $sheetXml,
        ];
    }

    private function parseSharedStrings(?string $xml): array
    {
        $strings = [];
        if (empty($xml)) {
            return $strings;
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadXML($xml);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        foreach ($xpath->query('//s:si') as $si) {
            $textNodes = $xpath->query('.//s:t', $si);
            $str = '';
            foreach ($textNodes as $t) {
                $str .= $t->nodeValue;
            }
            $strings[] = $str;
        }

        return $strings;
    }

    private function parseSheetRows(string $sheetXml, array $sharedStrings): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadXML($sheetXml);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        foreach ($xpath->query('//s:row') as $row) {
            $rIdx = (int)$row->getAttribute('r');
            $cols = [];
            foreach ($xpath->query('s:c', $row) as $c) {
                $coord = $c->getAttribute('r');
                $type = $c->getAttribute('t');
                preg_match('/([A-Z]+)/', $coord, $m);
                $colLetters = $m[1] ?? '';

                $vNode = $xpath->query('s:v', $c)->item(0);
                $val = $vNode ? $vNode->nodeValue : '';

                if ($type === 's' && is_numeric($val) && isset($sharedStrings[(int)$val])) {
                    $val = $sharedStrings[(int)$val];
                }

                $cols[$colLetters] = $val;
            }
            $rows[$rIdx] = $cols;
        }

        return $rows;
    }

    private function formatDate(string $val): string
    {
        if (empty($val)) {
            return now()->toDateString();
        }

        // If Excel serial number (e.g. 45000)
        if (is_numeric($val) && (int)$val > 10000 && (int)$val < 100000) {
            $days = (int)$val - 25569;
            return date('Y-m-d', $days * 86400);
        }

        // Check if YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            return $val;
        }

        $ts = strtotime($val);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return now()->toDateString();
    }
}
