<?php

namespace App\Http\Resources;

use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InventoryItem */
class InventoryItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'unit_cost' => $this->unit_cost,
            'unit_price' => $this->unit_price,
            'reorder_level' => $this->reorder_level,
            'quantity_on_hand' => $this->quantity_on_hand,
            'low_stock' => $this->quantity_on_hand <= $this->reorder_level,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ] : null),
            'movements' => $this->whenLoaded('movements', fn () => $this->movements->map(fn ($m) => [
                'id' => $m->id,
                'movement_type' => $m->movement_type,
                'quantity_delta' => $m->quantity_delta,
                'quantity_after' => $m->quantity_after,
                'unit_cost' => $m->unit_cost,
                'reference_number' => $m->reference_number,
                'lot_number' => $m->lot_number,
                'expires_at' => $m->expires_at?->toDateString(),
                'notes' => $m->notes,
                'performed_by' => $m->performer?->full_name,
                'moved_at' => $m->moved_at?->toIso8601String(),
            ])->values()->all()),
        ];
    }
}
