<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('patient_insurance_id')->nullable()->constrained('patient_insurances')->nullOnDelete();
            $table->string('status', 30)->default('issued')->index(); // draft, issued, partially_paid, paid, void
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);
            $table->dateTime('issued_at')->nullable()->index();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
        });

        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('patient_invoices')->cascadeOnDelete();
            $table->string('category', 40)->default('service'); // consultation, laboratory, imaging, pharmacy, procedure, inpatient, other
            $table->string('code', 60)->nullable();
            $table->string('description', 255);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 40)->unique();
            $table->foreignId('invoice_id')->constrained('patient_invoices')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('payer_type', 20)->default('patient'); // patient, insurance, adjustment
            $table->string('payment_method', 30)->default('cash'); // cash, mobile_money, card, bank_transfer, cheque, insurance_remittance, adjustment
            $table->decimal('amount', 12, 2);
            $table->string('reference_number', 100)->nullable();
            $table->dateTime('paid_at')->index();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'paid_at']);
        });

        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number', 40)->unique();
            $table->foreignId('invoice_id')->constrained('patient_invoices')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('patient_insurance_id')->nullable()->constrained('patient_insurances')->nullOnDelete();
            $table->string('payer_name', 200);
            $table->string('policy_number', 100)->nullable();
            $table->string('status', 30)->default('draft')->index(); // draft, submitted, approved, partially_approved, denied, paid
            $table->decimal('claimed_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('adjudicated_at')->nullable();
            $table->text('denial_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('patient_invoices');
    }
};
