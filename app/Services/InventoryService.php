<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, InventoryItem>
     */
    public function listItems(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = InventoryItem::query()->with(['warehouse:id,code,name']);

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['low_stock']) && filter_var($filters['low_stock'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereColumn('quantity_on_hand', '<=', 'reorder_level');
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(fn ($q) => $q->where('sku', 'like', $like)->orWhere('name', 'like', $like));
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function createWarehouse(array $data, User $actor, Request $request): Warehouse
    {
        $warehouse = Warehouse::create(array_merge($data, [
            'facility_id' => $data['facility_id'] ?? $actor->facility_id,
        ]));

        $this->audit->log(
            event: 'inventory.warehouse_created',
            category: 'inventory',
            user: $actor,
            auditable: $warehouse,
            success: true,
            metadata: ['warehouse_id' => $warehouse->id],
            request: $request,
        );

        return $warehouse;
    }

    /** @param array<string, mixed> $data */
    public function createItem(array $data, User $actor, Request $request): InventoryItem
    {
        return DB::transaction(function () use ($data, $actor, $request) {
            $initialQty = (int) ($data['quantity_on_hand'] ?? 0);
            $data['quantity_on_hand'] = 0;

            $item = InventoryItem::create($data);

            if ($initialQty > 0) {
                $this->recordMovement($item, [
                    'movement_type' => 'receipt',
                    'quantity' => $initialQty,
                    'unit_cost' => $item->unit_cost,
                    'notes' => 'Opening stock',
                ], $actor, $request);
            }

            $this->audit->log(
                event: 'inventory.item_created',
                category: 'inventory',
                user: $actor,
                auditable: $item,
                success: true,
                metadata: ['item_id' => $item->id, 'sku' => $item->sku],
                request: $request,
            );

            return $item->fresh(['warehouse:id,code,name', 'movements.performer:id,title,first_name,middle_name,last_name,suffix']);
        });
    }

    /** @param array<string, mixed> $data */
    public function recordMovement(InventoryItem $item, array $data, User $actor, Request $request): InventoryMovement
    {
        return DB::transaction(function () use ($item, $data, $actor, $request) {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $type = $data['movement_type'];
            $qty = (int) $data['quantity'];

            $delta = match ($type) {
                'receipt', 'return' => abs($qty),
                'issue', 'waste' => -abs($qty),
                'adjustment' => $qty, // signed +/- allowed for physical count adjustments
                default => throw ValidationException::withMessages(['movement_type' => ['Invalid movement type.']]),
            };

            $newBalance = $locked->quantity_on_hand + $delta;

            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ["Insufficient stock for {$locked->sku} ({$locked->quantity_on_hand} on hand)."],
                ]);
            }

            $locked->forceFill(['quantity_on_hand' => $newBalance])->save();

            $movement = InventoryMovement::create([
                'inventory_item_id' => $locked->id,
                'warehouse_id' => $data['warehouse_id'] ?? $locked->warehouse_id,
                'movement_type' => $type,
                'quantity_delta' => $delta,
                'quantity_after' => $newBalance,
                'unit_cost' => isset($data['unit_cost']) ? number_format((float) $data['unit_cost'], 2, '.', '') : $locked->unit_cost,
                'reference_number' => $data['reference_number'] ?? null,
                'lot_number' => $data['lot_number'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'performed_by' => $actor->id,
                'moved_at' => $data['moved_at'] ?? now(),
            ]);

            $this->audit->log(
                event: 'inventory.movement_recorded',
                category: 'inventory',
                user: $actor,
                auditable: $movement,
                success: true,
                metadata: [
                    'item_id' => $locked->id,
                    'movement_type' => $type,
                    'quantity_delta' => $delta,
                    'quantity_after' => $newBalance,
                ],
                request: $request,
            );

            return $movement->load('performer:id,title,first_name,middle_name,last_name,suffix');
        });
    }
}
