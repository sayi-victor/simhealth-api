<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 200)->index();
            $table->string('order_type', 30)->index(); // laboratory, imaging, procedure, service
            $table->string('category', 80)->nullable();
            $table->string('specimen_type', 60)->nullable();
            $table->string('units', 40)->nullable();
            $table->string('reference_range', 120)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('clinical_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->string('order_type', 30)->index(); // laboratory, imaging, procedure, service
            $table->string('priority', 20)->default('routine')->index(); // routine, urgent, stat
            $table->string('status', 30)->default('ordered')->index(); // ordered, collected, in_progress, completed, reviewed, cancelled
            $table->string('specimen_type', 60)->nullable();
            $table->string('specimen_number', 60)->nullable()->index();
            $table->dateTime('collected_at')->nullable();
            $table->dateTime('ordered_at')->index();
            $table->string('diagnosis', 255)->nullable();
            $table->text('clinical_history')->nullable();
            $table->text('patient_instructions')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'order_type', 'status'], 'clinical_orders_patient_type_status_idx');
        });

        Schema::create('clinical_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_order_id')->constrained('clinical_orders')->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained('order_catalog_items')->nullOnDelete();
            $table->string('item_code', 60);
            $table->string('item_name', 200);
            $table->string('status', 30)->default('pending')->index(); // pending, resulted, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('clinical_order_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_order_id')->constrained('clinical_orders')->cascadeOnDelete();
            $table->foreignId('clinical_order_item_id')->nullable()->constrained('clinical_order_items')->nullOnDelete();
            $table->string('result_code', 60)->nullable();
            $table->string('result_label', 200);
            $table->string('value', 255)->nullable();
            $table->string('units', 40)->nullable();
            $table->string('reference_range', 120)->nullable();
            $table->string('abnormal_flag', 20)->default('normal')->index(); // normal, low, high, critical, abnormal
            $table->text('findings')->nullable();
            $table->text('impression')->nullable();
            $table->text('comments')->nullable();
            $table->string('result_status', 30)->default('final'); // preliminary, final, corrected
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('resulted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resulted_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_order_results');
        Schema::dropIfExists('clinical_order_items');
        Schema::dropIfExists('clinical_orders');
        Schema::dropIfExists('order_catalog_items');
    }
};
