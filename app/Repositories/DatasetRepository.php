<?php

namespace App\Repositories;

use App\Models\Dataset;
use Illuminate\Database\Eloquent\Collection;

class DatasetRepository
{
    public function getAll(): Collection
    {
        return Dataset::withCount('items')->orderBy('created_at')->get();
    }

    public function findBySlug(string $slug): ?Dataset
    {
        return Dataset::withCount('items')->where('slug', $slug)->first();
    }

    public function findById(int $id): ?Dataset
    {
        return Dataset::find($id);
    }

    public function createOrUpdate(array $attributes, array $values): Dataset
    {
        return Dataset::updateOrCreate($attributes, $values);
    }
}
