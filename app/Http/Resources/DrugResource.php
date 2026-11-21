<?php

namespace App\Http\Resources;

use App\Models\Drug;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Drug */
class DrugResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $totalStock = $this->relationLoaded('lots')
            ? (int) $this->lots->sum('quantity_on_hand')
            : null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'generic_name' => $this->generic_name,
            'rxnorm_code' => $this->rxnorm_code,
            'form' => $this->form,
            'strength' => $this->strength,
            'route' => $this->route,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
            'reorder_level' => $this->reorder_level,
            'requires_prescription' => $this->requires_prescription,
            'is_controlled' => $this->is_controlled,
            'is_active' => $this->is_active,
            'total_on_hand' => $totalStock,
            'low_stock' => $totalStock !== null ? $totalStock <= $this->reorder_level : null,
            'lots' => $this->whenLoaded('lots', fn () => $this->lots->map(fn ($lot) => [
                'id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'manufacturer' => $lot->manufacturer,
                'quantity_on_hand' => $lot->quantity_on_hand,
                'unit_cost' => $lot->unit_cost,
                'expires_at' => $lot->expires_at?->toDateString(),
                'received_at' => $lot->received_at?->toDateString(),
            ])->values()->all()),
        ];
    }
}
