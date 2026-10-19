<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\DatasetItem;
use App\Repositories\DatasetRepository;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DatasetService
{
    public function __construct(
        private readonly DatasetRepository $datasetRepo
    ) {}

    public function getAll(): Collection
    {
        return $this->datasetRepo->getAll();
    }

    public function getBySlug(string $slug): Dataset
    {
        $dataset = $this->datasetRepo->findBySlug($slug);
        if (!$dataset) {
            throw new Exception("Dataset dengan slug '{$slug}' tidak ditemukan.", 404);
        }
        return $dataset;
    }

    public function getSummary(string $slug): array
    {
        $dataset = $this->getBySlug($slug);

        $totalItems = DatasetItem::where('dataset_id', $dataset->id)->count();
        $totalValue = (float)DatasetItem::where('dataset_id', $dataset->id)->sum('value');

        $statusCounts = DatasetItem::where('dataset_id', $dataset->id)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $categoryCounts = DatasetItem::where('dataset_id', $dataset->id)
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        return [
            'dataset' => $dataset,
            'total_items' => $totalItems,
            'total_value' => $totalValue,
            'status_counts' => $statusCounts,
            'category_counts' => $categoryCounts,
        ];
    }
}
