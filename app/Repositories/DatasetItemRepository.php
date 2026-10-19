<?php

namespace App\Repositories;

use App\Models\DatasetItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DatasetItemRepository
{
    public function paginate(int $datasetId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DatasetItem::where('dataset_id', $datasetId);

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', $search)
                  ->orWhere('code', 'ilike', $search)
                  ->orWhere('unit', 'ilike', $search)
                  ->orWhere('notes', 'ilike', $search);
            });
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = strtolower($filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['id', 'code', 'name', 'category', 'unit', 'date', 'value', 'status'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?DatasetItem
    {
        return DatasetItem::find($id);
    }

    public function create(array $data): DatasetItem
    {
        return DatasetItem::create($data);
    }

    public function update(DatasetItem $item, array $data): DatasetItem
    {
        $item->update($data);
        return $item->fresh();
    }

    public function delete(DatasetItem $item): bool
    {
        return $item->delete();
    }

    public function upsertItem(int $datasetId, array $data): DatasetItem
    {
        return DatasetItem::updateOrCreate(
            [
                'dataset_id' => $datasetId,
                'code' => $data['code'],
            ],
            $data
        );
    }
}
