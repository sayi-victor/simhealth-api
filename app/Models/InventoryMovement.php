<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'warehouse_id',
        'movement_type',
        'quantity_delta',
        'quantity_after',
        'unit_cost',
        'reference_number',
        'lot_number',
        'expires_at',
        'notes',
        'performed_by',
        'moved_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'quantity_after' => 'integer',
            'unit_cost' => 'decimal:2',
            'expires_at' => 'date',
            'moved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<User, $this> */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
