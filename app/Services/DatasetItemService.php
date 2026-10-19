<?php

namespace App\Services;

use App\Models\DatasetItem;
use App\Repositories\DatasetItemRepository;
use App\Repositories\DatasetRepository;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DatasetItemService
{
    public function __construct(
        private readonly DatasetRepository $datasetRepo,
        private readonly DatasetItemRepository $itemRepo,
    ) {}

    public function listItems(string $datasetSlug, array $filters = []): array
    {
        $dataset = $this->datasetRepo->findBySlug($datasetSlug);
        if (!$dataset) {
            throw new Exception("Dataset '{$datasetSlug}' tidak ditemukan.", 404);
        }

        $perPage = (int)($filters['per_page'] ?? 15);
        $paginator = $this->itemRepo->paginate($dataset->id, $filters, $perPage);

        return [
            'dataset' => $dataset,
            'paginator' => $paginator,
        ];
    }

    public function createItem(string $datasetSlug, array $data): DatasetItem
    {
        $dataset = $this->datasetRepo->findBySlug($datasetSlug);
        if (!$dataset) {
            throw new Exception("Dataset '{$datasetSlug}' tidak ditemukan.", 404);
        }

        $data['dataset_id'] = $dataset->id;

        // If category or status is new, optionally append to dataset's list
        $this->syncDatasetOptions($dataset, $data['category'] ?? null, $data['status'] ?? null);

        return $this->itemRepo->create($data);
    }

    public function updateItem(int $id, array $data): DatasetItem
    {
        $item = $this->itemRepo->findById($id);
        if (!$item) {
            throw new Exception("Item tidak ditemukan.", 404);
        }

        $dataset = $item->dataset;
        if ($dataset) {
            $this->syncDatasetOptions($dataset, $data['category'] ?? null, $data['status'] ?? null);
        }

        return $this->itemRepo->update($item, $data);
    }

    public function deleteItem(int $id): bool
    {
        $item = $this->itemRepo->findById($id);
        if (!$item) {
            throw new Exception("Item tidak ditemukan.", 404);
        }

        return $this->itemRepo->delete($item);
    }

    private function syncDatasetOptions($dataset, ?string $newCategory, ?string $newStatus): void
    {
        $dirty = false;
        $categories = $dataset->categories ?? [];
        $statuses = $dataset->statuses ?? [];

        if (!empty($newCategory) && !in_array($newCategory, $categories, true)) {
            $categories[] = $newCategory;
            $dataset->categories = $categories;
            $dirty = true;
        }

        if (!empty($newStatus) && !in_array($newStatus, $statuses, true)) {
            $statuses[] = $newStatus;
            $dataset->statuses = $statuses;
            $dirty = true;
        }

        if ($dirty) {
            $dataset->save();
        }
    }
}
