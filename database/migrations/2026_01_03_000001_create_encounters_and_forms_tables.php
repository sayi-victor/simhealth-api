<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->string('encounter_number', 40)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('billing_facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('insurance_id')->nullable()->constrained('patient_insurances')->nullOnDelete();
            $table->dateTime('encounter_date')->index();
            $table->date('onset_date')->nullable();
            $table->string('visit_category', 60)->default('outpatient')->index();
            $table->string('sensitivity', 30)->default('normal');
            $table->string('status', 30)->default('open')->index(); // open, in_progress, signed, closed, cancelled
            $table->string('shift', 10)->nullable();
            $table->string('room_number', 40)->nullable();
            $table->text('reason')->nullable();
            $table->string('referral_source', 80)->nullable();
            $table->string('nhis_vcode', 20)->nullable();
            $table->string('price_level', 40)->default('standard');
            $table->text('billing_note')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'encounter_date']);
        });

        Schema::create('encounter_vitals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->dateTime('recorded_at')->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('bp_systolic')->nullable();
            $table->unsignedSmallInteger('bp_diastolic')->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->unsignedSmallInteger('respiration')->nullable();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->string('temp_method', 30)->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('height_cm', 6, 2)->nullable();
            $table->decimal('bmi', 5, 1)->nullable();
            $table->string('bmi_status', 30)->nullable();
            $table->decimal('oxygen_saturation', 5, 2)->nullable();
            $table->string('oxygen_sat_condition', 50)->nullable();
            $table->decimal('waist_cm', 5, 2)->nullable();
            $table->decimal('head_cm', 5, 2)->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->string('triage_level', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['encounter_id', 'recorded_at']);
        });

        Schema::create('encounter_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('note_type', 40)->default('consultation')->index(); // consultation, soap, nursing, observation, discharge, referral
            $table->string('title', 255)->nullable();
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_signed')->default(false)->index();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('authored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['encounter_id', 'note_type']);
        });

        Schema::create('form_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 150);
            $table->string('category', 60)->default('clinical')->index();
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_definition_id')->constrained('form_definitions')->cascadeOnDelete();
            $table->string('field_key', 60);
            $table->string('label', 150);
            $table->string('group_name', 80)->default('General');
            $table->string('field_type', 30)->default('text'); // text, textarea, number, date, datetime, select, checkbox, radio
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('options')->nullable();
            $table->string('default_value', 255)->nullable();
            $table->string('help_text', 255)->nullable();
            $table->timestamps();

            $table->unique(['form_definition_id', 'field_key']);
            $table->index(['form_definition_id', 'sort_order']);
        });

        Schema::create('encounter_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('form_definition_id')->constrained('form_definitions')->restrictOnDelete();
            $table->string('status', 30)->default('completed')->index(); // draft, completed, signed
            $table->json('data');
            $table->foreignId('filled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['encounter_id', 'form_definition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_forms');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('form_definitions');
        Schema::dropIfExists('encounter_notes');
        Schema::dropIfExists('encounter_vitals');
        Schema::dropIfExists('encounters');
    }
};
