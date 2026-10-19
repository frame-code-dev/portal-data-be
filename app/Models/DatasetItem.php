<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset_id',
        'code',
        'name',
        'category',
        'unit',
        'date',
        'value',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'value' => 'float',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
