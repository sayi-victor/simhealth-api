<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('location', 200)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('sku', 60)->unique();
            $table->string('name', 200)->index();
            $table->string('category', 60)->default('consumable')->index(); // consumable, lab_reagent, surgical, equipment, general
            $table->string('unit', 40)->default('unit');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('reorder_level')->default(10);
            $table->integer('quantity_on_hand')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'category']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('movement_type', 30)->index(); // receipt, issue, adjustment, return, waste
            $table->integer('quantity_delta');
            $table->integer('quantity_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference_number', 80)->nullable()->index();
            $table->string('lot_number', 60)->nullable();
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('moved_at')->index();
            $table->timestamps();

            $table->index(['inventory_item_id', 'moved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('warehouses');
    }
};
