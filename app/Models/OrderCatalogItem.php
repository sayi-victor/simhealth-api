<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderCatalogItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'order_type',
        'category',
        'specimen_type',
        'units',
        'reference_range',
        'unit_price',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
