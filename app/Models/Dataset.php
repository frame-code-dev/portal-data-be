<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'value_label',
        'categories',
        'statuses',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'statuses' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DatasetItem::class);
    }
}
