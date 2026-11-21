<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 40)->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('fax', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('street', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 60)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('federal_ein', 25)->nullable();
            $table->string('facility_npi', 20)->nullable()->index();
            $table->string('tax_id_type', 30)->nullable();
            $table->unsignedSmallInteger('pos_code')->nullable();
            $table->string('color', 7)->nullable();
            $table->string('tagline', 180)->nullable();
            $table->boolean('is_service_location')->default(true);
            $table->boolean('is_billing_location')->default(false);
            $table->boolean('accepts_assignment')->default(false);
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->string('username', 100)->unique();
            $table->string('email', 255)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('title', 30)->nullable();
            $table->string('suffix', 30)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_provider')->default(false)->index();
            $table->boolean('show_in_calendar')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->date('password_expires_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
        });

        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('specialty', 120)->nullable()->index();
            $table->string('physician_type', 60)->nullable();
            $table->string('npi', 20)->nullable()->index();
            $table->string('taxonomy', 30)->nullable();
            $table->string('state_license_number', 50)->nullable();
            $table->string('federal_drug_id', 50)->nullable();
            $table->string('federal_tax_id', 50)->nullable();
            $table->string('upin', 50)->nullable();
            $table->string('billing_name', 255)->nullable();
            $table->boolean('cpoe_enabled')->default(false);
            $table->string('default_warehouse', 60)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday .. 6 = Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'facility_id', 'day_of_week'], 'provider_schedules_unique_day');
            $table->index(['day_of_week', 'is_active']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('provider_schedules');
        Schema::dropIfExists('provider_profiles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('facilities');
    }
};
