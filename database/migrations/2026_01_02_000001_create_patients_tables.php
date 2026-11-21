<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('mrn', 50)->unique();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('primary_provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('referring_provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 30)->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->date('date_of_birth')->nullable()->index();
            $table->string('sex', 20)->default('unknown')->index();
            $table->string('marital_status', 30)->nullable();
            $table->string('nationality', 80)->nullable();
            $table->string('religion', 60)->nullable();
            $table->string('language', 60)->nullable();
            $table->string('race', 60)->nullable();
            $table->string('ethnicity', 60)->nullable();
            $table->string('occupation', 120)->nullable();
            $table->string('national_id', 50)->nullable()->index();
            $table->string('passport_number', 50)->nullable();
            $table->string('drivers_license', 50)->nullable();
            $table->string('phone_cell', 30)->nullable()->index();
            $table->string('phone_home', 30)->nullable();
            $table->string('phone_work', 30)->nullable();
            $table->string('email', 255)->nullable()->index();
            $table->string('street', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 60)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->date('registration_date')->nullable()->index();
            $table->string('registration_type', 40)->nullable();
            $table->string('referral_source', 80)->nullable();
            $table->string('price_level', 40)->default('standard');
            $table->text('billing_note')->nullable();
            $table->text('clinical_alert')->nullable();
            $table->dateTime('deceased_at')->nullable();
            $table->string('deceased_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
        });

        Schema::create('patient_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('contact_type', 30)->index(); // emergency, guardian, next_of_kin, mother, father, other
            $table->string('relationship', 60)->nullable();
            $table->string('full_name', 200);
            $table->string('sex', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('country', 60)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['patient_id', 'contact_type']);
        });

        Schema::create('patient_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained('patients')->cascadeOnDelete();
            $table->boolean('allow_sms')->default(true);
            $table->boolean('allow_email')->default(false);
            $table->boolean('allow_voice')->default(false);
            $table->boolean('allow_mail')->default(false);
            $table->boolean('hipaa_notice_received')->default(false);
            $table->string('hipaa_message', 60)->nullable();
            $table->boolean('allow_immunization_registry')->default(false);
            $table->boolean('allow_health_info_exchange')->default(false);
            $table->boolean('advance_directive_completed')->default(false);
            $table->date('advance_directive_reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_employers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('employer_name', 255);
            $table->string('occupation', 120)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('employment_status', 60)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('street', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 60)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 60)->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->timestamps();

            $table->index(['patient_id', 'is_current']);
        });

        Schema::create('patient_insurances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('tier', 20)->default('primary')->index(); // primary, secondary, tertiary
            $table->string('provider_name', 255);
            $table->string('plan_name', 255)->nullable();
            $table->string('policy_number', 100)->nullable()->index();
            $table->string('group_number', 100)->nullable();
            $table->string('policy_type', 40)->nullable();
            $table->string('subscriber_first_name', 100)->nullable();
            $table->string('subscriber_middle_name', 100)->nullable();
            $table->string('subscriber_last_name', 100)->nullable();
            $table->string('subscriber_relationship', 40)->default('self');
            $table->date('subscriber_dob')->nullable();
            $table->string('subscriber_sex', 20)->nullable();
            $table->string('subscriber_phone', 30)->nullable();
            $table->string('subscriber_national_id', 50)->nullable();
            $table->decimal('copay', 10, 2)->nullable();
            $table->decimal('maximum_bill', 12, 2)->nullable();
            $table->boolean('accept_assignment')->default(true);
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'tier', 'is_active']);
        });

        Schema::create('patient_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('type', 40)->index(); // medical_problem, allergy, medication, surgery, dental
            $table->string('title', 255);
            $table->string('code', 60)->nullable()->index();
            $table->string('severity', 40)->nullable();
            $table->string('reaction', 255)->nullable();
            $table->date('onset_date')->nullable();
            $table->date('resolved_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('comments')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'type', 'is_active']);
        });

        Schema::create('patient_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained('patients')->cascadeOnDelete();
            $table->json('lifestyle')->nullable();
            $table->json('family_history')->nullable();
            $table->json('surgical_history')->nullable();
            $table->json('preventive_exams')->nullable();
            $table->json('mental_health')->nullable();
            $table->text('additional_notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_histories');
        Schema::dropIfExists('patient_issues');
        Schema::dropIfExists('patient_insurances');
        Schema::dropIfExists('patient_employers');
        Schema::dropIfExists('patient_consents');
        Schema::dropIfExists('patient_contacts');
        Schema::dropIfExists('patients');
    }
};
