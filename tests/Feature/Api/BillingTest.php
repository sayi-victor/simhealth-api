<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\PatientInvoice;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingTest extends TestCase
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

    public function test_unauthenticated_and_unauthorized_billing_requests_are_rejected(): void
    {
        $this->getJson('/api/invoices')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        // Front desk can view invoices, but cannot create/manage or adjudicate claims
        $this->actingAsRole('front_desk');
        $this->getJson('/api/invoices')->assertOk();
        $this->postJson('/api/invoices', [])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_invoice_payment_and_insurance_claim_remittance_flow(): void
    {
        $this->actingAsRole('billing');
        $patient = Patient::factory()->create();

        // 1. Create invoice with 2 line items (100 + 2*45 = 190, minus 10 discount = 180.00)
        $invRes = $this->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'discount_amount' => '10.00',
            'items' => [
                ['category' => 'consultation', 'code' => 'CONS-01', 'description' => 'Outpatient Consultation', 'quantity' => 1, 'unit_price' => '100.00'],
                ['category' => 'laboratory', 'code' => 'LAB-CBC', 'description' => 'Full Blood Count', 'quantity' => 2, 'unit_price' => '45.00'],
            ],
        ]);

        $invRes->assertCreated()
            ->assertJsonPath('data.subtotal', '190.00')
            ->assertJsonPath('data.total_amount', '180.00')
            ->assertJsonPath('data.balance_due', '180.00')
            ->assertJsonPath('data.status', 'issued');

        $invoiceId = $invRes->json('data.id');
        $this->assertSame(sprintf('INV-%06d', $invoiceId), $invRes->json('data.invoice_number'));

        // 2. Patient pays 60.00 copay -> partially_paid, balance 120.00
        $payRes = $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'payer_type' => 'patient',
            'payment_method' => 'mobile_money',
            'amount' => '60.00',
            'reference_number' => 'MOMO-12345',
        ]);
        $payRes->assertCreated()->assertJsonPath('data.amount', '60.00');
        $this->assertStringStartsWith('RCP-', $payRes->json('data.receipt_number'));

        $this->assertSame('partially_paid', PatientInvoice::findOrFail($invoiceId)->status);
        $this->assertSame('120.00', PatientInvoice::findOrFail($invoiceId)->balance_due);

        // Overpayment (> 120.00) rejected with 422
        $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'payment_method' => 'cash',
            'amount' => '200.00',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // 3. Submit insurance claim for remaining 120.00
        $claimRes = $this->postJson('/api/claims', [
            'invoice_id' => $invoiceId,
            'payer_name' => 'NHIS',
            'policy_number' => 'NHIS-001122',
            'claimed_amount' => '120.00',
        ]);
        $claimRes->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.claimed_amount', '120.00');
        $claimId = $claimRes->json('data.id');
        $this->assertSame(sprintf('CLM-%06d', $claimId), $claimRes->json('data.claim_number'));

        // 4. Adjudicate claim as paid with post_payment=true -> automatically settles invoice to paid!
        $this->postJson("/api/claims/{$claimId}/adjudicate", [
            'status' => 'paid',
            'approved_amount' => '120.00',
            'paid_amount' => '120.00',
            'post_payment' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $settled = PatientInvoice::findOrFail($invoiceId);
        $this->assertSame('paid', $settled->status);
        $this->assertSame('0.00', $settled->balance_due);
    }

    public function test_can_void_unpaid_invoice_but_not_paid_invoice(): void
    {
        $this->actingAsRole('billing');
        $patient = Patient::factory()->create();

        $inv = $this->postJson('/api/invoices', [
            'patient_id' => $patient->id,
            'items' => [['description' => 'Consult', 'quantity' => 1, 'unit_price' => '50.00']],
        ])->json('data.id');

        $this->postJson("/api/invoices/{$inv}/void")
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.balance_due', '0.00');
    }
}
