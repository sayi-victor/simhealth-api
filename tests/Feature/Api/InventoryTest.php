<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function actingAsRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail()->id);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_unauthenticated_and_unauthorized_inventory_requests_are_rejected(): void
    {
        $this->getJson('/api/inventory-items')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->actingAsRole('front_desk');
        $this->getJson('/api/inventory-items')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_warehouse_item_and_stock_movement_ledger_flow(): void
    {
        $this->actingAsRole('inventory');

        // 1. Create warehouse
        $whRes = $this->postJson('/api/warehouses', [
            'code' => 'MAIN-STORE',
            'name' => 'Central Medical Stores',
            'location' => 'Ground Floor Block B',
        ]);
        $whRes->assertCreated()->assertJsonPath('data.code', 'MAIN-STORE');
        $warehouseId = $whRes->json('data.id');

        // 2. Create inventory item with opening stock of 50
        $itemRes = $this->postJson('/api/inventory-items', [
            'warehouse_id' => $warehouseId,
            'sku' => 'CON-GLOVE-M',
            'name' => 'Nitrile Exam Gloves (Medium, Box of 100)',
            'category' => 'consumable',
            'unit' => 'box',
            'unit_cost' => '35.00',
            'unit_price' => '45.00',
            'reorder_level' => 15,
            'quantity_on_hand' => 50,
        ]);
        $itemRes->assertCreated()
            ->assertJsonPath('data.quantity_on_hand', 50)
            ->assertJsonPath('data.low_stock', false);
        $itemId = $itemRes->json('data.id');

        // 3. Issue 40 boxes -> balance 10 (triggers low_stock!)
        $this->postJson("/api/inventory-items/{$itemId}/movements", [
            'movement_type' => 'issue',
            'quantity' => 40,
            'reference_number' => 'REQ-001',
            'notes' => 'Issued to OPD triage',
        ])
            ->assertCreated()
            ->assertJsonPath('data.quantity_delta', -40)
            ->assertJsonPath('data.quantity_after', 10);

        // Low-stock filter returns the item
        $this->getJson('/api/inventory-items?low_stock=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'CON-GLOVE-M')
            ->assertJsonPath('data.0.low_stock', true);

        // Over-issuing (> 10) rejected with 422
        $this->postJson("/api/inventory-items/{$itemId}/movements", [
            'movement_type' => 'issue',
            'quantity' => 25,
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // 4. Receive 30 boxes -> balance 40
        $this->postJson("/api/inventory-items/{$itemId}/movements", [
            'movement_type' => 'receipt',
            'quantity' => 30,
            'reference_number' => 'PO-2026-88',
            'lot_number' => 'LOT-GLV-9',
        ])
            ->assertCreated()
            ->assertJsonPath('data.quantity_delta', 30)
            ->assertJsonPath('data.quantity_after', 40);

        // Item detail shows movement ledger (opening + issue + receipt = 3 movements)
        $this->getJson("/api/inventory-items/{$itemId}")
            ->assertOk()
            ->assertJsonPath('data.quantity_on_hand', 40)
            ->assertJsonCount(3, 'data.movements');
    }
}
