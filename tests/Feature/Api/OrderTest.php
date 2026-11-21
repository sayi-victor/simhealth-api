<?php

namespace Tests\Feature\Api;

use App\Models\OrderCatalogItem;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\OrderCatalogSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RbacSeeder::class, OrderCatalogSeeder::class]);
    }

    private function actingAsRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail()->id);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_unauthenticated_order_requests_return_401(): void
    {
        $this->getJson('/api/orders')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_rbac_separates_ordering_and_resulting(): void
    {
        $patient = Patient::factory()->create();
        $cbc = OrderCatalogItem::where('code', 'LAB-CBC')->firstOrFail();

        // Lab cannot create clinical orders
        $this->actingAsRole('lab');
        $this->postJson('/api/orders', [
            'patient_id' => $patient->id,
            'order_type' => 'laboratory',
            'items' => [['catalog_item_id' => $cbc->id]],
        ])->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');

        // Clinician can create order, but cannot post lab results
        $this->actingAsRole('clinician');
        $orderRes = $this->postJson('/api/orders', [
            'patient_id' => $patient->id,
            'order_type' => 'laboratory',
            'items' => [['catalog_item_id' => $cbc->id]],
        ]);
        $orderRes->assertCreated();
        $orderId = $orderRes->json('data.id');

        $this->postJson("/api/orders/{$orderId}/results", [
            'result_label' => 'Hemoglobin',
            'value' => '13.4',
        ])->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_full_order_collect_result_and_clinician_review_flow(): void
    {
        $patient = Patient::factory()->create();
        $cbc = OrderCatalogItem::where('code', 'LAB-CBC')->firstOrFail();
        $fbg = OrderCatalogItem::where('code', 'LAB-FBG')->firstOrFail();

        // 1. Clinician views catalog and places multi-item lab order
        $this->actingAsRole('clinician');

        $this->getJson('/api/order-catalog?order_type=laboratory')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $createRes = $this->postJson('/api/orders', [
            'patient_id' => $patient->id,
            'order_type' => 'laboratory',
            'priority' => 'urgent',
            'diagnosis' => 'Suspected anemia & diabetes screening',
            'items' => [
                ['catalog_item_id' => $cbc->id],
                ['catalog_item_id' => $fbg->id],
            ],
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('data.status', 'ordered')
            ->assertJsonCount(2, 'data.items');

        $orderId = $createRes->json('data.id');
        $item1Id = $createRes->json('data.items.0.id');
        $item2Id = $createRes->json('data.items.1.id');
        $this->assertSame(sprintf('ORD-%06d', $orderId), $createRes->json('data.order_number'));

        // 2. Lab collects specimen
        $this->actingAsRole('lab');
        $this->postJson("/api/orders/{$orderId}/collect", [
            'specimen_type' => 'Whole Blood (EDTA)',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'collected');

        // 3. Lab posts first result -> order transitions to in_progress
        $this->postJson("/api/orders/{$orderId}/results", [
            'clinical_order_item_id' => $item1Id,
            'value' => '9.2',
            'units' => 'g/dL',
            'reference_range' => '12.0 - 16.5',
            'abnormal_flag' => 'low',
        ])
            ->assertCreated()
            ->assertJsonPath('data.abnormal_flag', 'low');

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        // 4. Lab posts second result -> all items resulted -> order transitions to completed
        $this->postJson("/api/orders/{$orderId}/results", [
            'clinical_order_item_id' => $item2Id,
            'value' => '8.4',
            'units' => 'mmol/L',
            'reference_range' => '3.9 - 5.5',
            'abnormal_flag' => 'high',
        ])->assertCreated();

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonCount(2, 'data.results');

        // 5. Clinician reviews & signs off the completed order
        $this->actingAsRole('clinician');
        $this->postJson("/api/orders/{$orderId}/review")
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed');
    }

    public function test_order_validation_requires_at_least_one_item(): void
    {
        $this->actingAsRole('clinician');
        $patient = Patient::factory()->create();

        $this->postJson('/api/orders', [
            'patient_id' => $patient->id,
            'order_type' => 'imaging',
            'items' => [],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['items']]]);
    }
}
