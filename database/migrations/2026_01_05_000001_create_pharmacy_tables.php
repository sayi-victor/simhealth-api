<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 200)->index();
            $table->string('generic_name', 200)->nullable()->index();
            $table->string('rxnorm_code', 40)->nullable()->index();
            $table->string('form', 40)->default('tablet');
            $table->string('strength', 60)->nullable();
            $table->string('route', 40)->default('oral');
            $table->string('unit', 30)->default('tablet');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('reorder_level')->default(10);
            $table->boolean('requires_prescription')->default(true);
            $table->boolean('is_controlled')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('drug_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained('drugs')->cascadeOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->string('lot_number', 60)->index();
            $table->string('manufacturer', 150)->nullable();
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->date('received_at')->nullable();
            $table->string('invoice_number', 60)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['drug_id', 'expires_at'], 'drug_lots_fefo_idx');
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number', 40)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('drug_id')->nullable()->constrained('drugs')->nullOnDelete();
            $table->string('drug_name', 200);
            $table->string('dosage', 100);
            $table->string('route', 40)->nullable();
            $table->string('frequency', 80);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->unsignedInteger('quantity_prescribed');
            $table->unsignedInteger('quantity_dispensed')->default(0);
            $table->unsignedTinyInteger('refills_allowed')->default(0);
            $table->boolean('is_prn')->default(false);
            $table->boolean('allow_substitution')->default(true);
            $table->boolean('inhouse')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('indication', 255)->nullable();
            $table->text('instructions')->nullable();
            $table->string('status', 30)->default('pending')->index(); // pending, partially_dispensed, dispensed, cancelled
            $table->dateTime('prescribed_at')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
        });

        Schema::create('prescription_dispensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained('drugs')->restrictOnDelete();
            $table->foreignId('drug_lot_id')->constrained('drug_lots')->restrictOnDelete();
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->dateTime('dispensed_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['prescription_id', 'dispensed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_dispensations');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('drug_lots');
        Schema::dropIfExists('drugs');
    }
};
