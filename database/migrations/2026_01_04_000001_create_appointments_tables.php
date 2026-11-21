<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 120);
            $table->string('color', 7)->default('#0f766e');
            $table->unsignedSmallInteger('default_duration_minutes')->default(30);
            $table->unsignedSmallInteger('daily_limit')->nullable();
            $table->boolean('provider_mandatory')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('appointment_categories')->nullOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->string('title', 150)->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->boolean('all_day')->default(false);
            $table->string('status', 30)->default('scheduled')->index();
            $table->string('room', 40)->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('send_sms_reminder')->default(false);
            $table->boolean('send_email_reminder')->default(false);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider_id', 'starts_at', 'ends_at'], 'appointments_provider_window_idx');
            $table->index(['facility_id', 'starts_at'], 'appointments_facility_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_categories');
    }
};
