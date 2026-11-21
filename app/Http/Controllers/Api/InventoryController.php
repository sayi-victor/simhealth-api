<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function indexWarehouses(): JsonResponse
    {
        return response()->json([
            'data' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeWarehouse(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('warehouses', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:200'],
        ]);

        $warehouse = $this->inventory->createWarehouse($validated, $actor, $request);

        return response()->json(['data' => $warehouse], 201);
    }

    public function indexItems(Request $request): AnonymousResourceCollection
    {
        return InventoryItemResource::collection(
            $this->inventory->listItems($request->only([
                'search',
                'category',
                'warehouse_id',
                'low_stock',
                'per_page',
            ]))
        );
    }

    public function storeItem(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'sku' => ['required', 'string', 'max:60', Rule::unique('inventory_items', 'sku')],
            'name' => ['required', 'string', 'max:200'],
            'category' => ['sometimes', Rule::in(['consumable', 'lab_reagent', 'surgical', 'equipment', 'general'])],
            'unit' => ['sometimes', 'string', 'max:40'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'reorder_level' => ['sometimes', 'integer', 'min:0'],
            'quantity_on_hand' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = $this->inventory->createItem($validated, $actor, $request);

        return (new InventoryItemResource($item))->response()->setStatusCode(201);
    }

    public function showItem(InventoryItem $item): InventoryItemResource
    {
        $item->load(['warehouse:id,code,name', 'movements.performer:id,title,first_name,middle_name,last_name,suffix']);

        return new InventoryItemResource($item);
    }

    public function storeMovement(Request $request, InventoryItem $item): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'movement_type' => ['required', Rule::in(['receipt', 'issue', 'adjustment', 'return', 'waste'])],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'lot_number' => ['nullable', 'string', 'max:60'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $movement = $this->inventory->recordMovement($item, $validated, $actor, $request);

        return response()->json([
            'data' => [
                'id' => $movement->id,
                'inventory_item_id' => $movement->inventory_item_id,
                'movement_type' => $movement->movement_type,
                'quantity_delta' => $movement->quantity_delta,
                'quantity_after' => $movement->quantity_after,
                'reference_number' => $movement->reference_number,
                'performed_by' => $movement->performer?->full_name,
                'moved_at' => $movement->moved_at?->toIso8601String(),
            ],
        ], 201);
    }
}
