<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'warehouse_id',
        'sku',
        'name',
        'category',
        'unit',
        'unit_cost',
        'unit_price',
        'reorder_level',
        'quantity_on_hand',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'reorder_level' => 'integer',
            'quantity_on_hand' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->orderByDesc('moved_at');
    }
}
