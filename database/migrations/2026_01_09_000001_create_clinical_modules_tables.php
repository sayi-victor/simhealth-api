<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('category', 40)->default('general')->index(); // general, maternity, pediatric, icu, surgical, private, emergency
            $table->string('floor', 60)->nullable();
            $table->decimal('daily_rate', 12, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            $table->string('bed_number', 40);
            $table->string('status', 30)->default('available')->index(); // available, occupied, maintenance, cleaning
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['ward_id', 'bed_number']);
        });

        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->string('admission_number', 40)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('ward_id')->constrained('wards')->restrictOnDelete();
            $table->foreignId('bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->foreignId('admitting_provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('discharging_provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admission_type', 30)->default('elective'); // elective, emergency, maternity, transfer, observation
            $table->string('status', 30)->default('admitted')->index(); // admitted, discharged, transferred, cancelled
            $table->dateTime('admitted_at')->index();
            $table->date('expected_discharge_date')->nullable();
            $table->dateTime('discharged_at')->nullable();
            $table->string('admission_diagnosis', 255)->nullable();
            $table->text('admission_notes')->nullable();
            $table->string('discharge_diagnosis', 255)->nullable();
            $table->text('discharge_summary')->nullable();
            $table->string('discharge_disposition', 40)->nullable(); // home, transferred, against_medical_advice, deceased
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
        });

        Schema::create('surgeries', function (Blueprint $table) {
            $table->id();
            $table->string('surgery_number', 40)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignId('admission_id')->nullable()->constrained('admissions')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('surgeon_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('anesthetist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('procedure_name', 200);
            $table->string('procedure_code', 60)->nullable();
            $table->string('theater_room', 60)->nullable();
            $table->string('priority', 20)->default('elective'); // elective, urgent, emergency
            $table->string('status', 30)->default('scheduled')->index(); // scheduled, in_progress, completed, cancelled
            $table->dateTime('scheduled_at')->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('anesthesia_type', 30)->nullable(); // general, regional, local, sedation
            $table->boolean('consent_signed')->default(false);
            $table->string('pre_op_diagnosis', 255)->nullable();
            $table->string('post_op_diagnosis', 255)->nullable();
            $table->text('operative_notes')->nullable();
            $table->text('complications')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('immunizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vaccine_name', 150)->index();
            $table->string('cvx_code', 20)->nullable();
            $table->unsignedTinyInteger('dose_number')->nullable();
            $table->decimal('dose_amount', 8, 2)->nullable();
            $table->string('dose_unit', 30)->nullable();
            $table->string('route', 30)->nullable(); // im, sc, oral, intradermal, intranasal
            $table->string('site', 60)->nullable();
            $table->string('manufacturer', 120)->nullable();
            $table->string('lot_number', 60)->nullable();
            $table->date('expires_at')->nullable();
            $table->dateTime('administered_at')->index();
            $table->date('next_due_date')->nullable();
            $table->string('status', 30)->default('completed')->index(); // completed, refused, not_administered
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'administered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immunizations');
        Schema::dropIfExists('surgeries');
        Schema::dropIfExists('admissions');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('wards');
    }
};
