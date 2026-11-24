<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete(); // null = platform default
            $t->string('name', 100);
            $t->string('code', 50);
            $t->string('required_license', 10)->nullable(); // e.g. A, B, C, CE
            $t->decimal('default_capacity', 10, 2)->nullable();
            $t->string('capacity_unit', 10)->default('kg');
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_type_id')->constrained()->restrictOnDelete();
            $t->string('registration_number', 32);
            $t->string('make', 60);
            $t->string('model', 60);
            $t->unsignedSmallInteger('year')->nullable();
            $t->string('color', 30)->nullable();
            $t->string('vin', 32)->nullable();
            $t->string('fuel_type', 20);
            $t->decimal('capacity', 10, 2)->default(0);
            $t->string('capacity_unit', 10)->default('kg');
            $t->unsignedInteger('odometer')->default(0);
            $t->string('status', 24)->default('available')->index();
            $t->string('image_path')->nullable();
            $t->string('insurance_provider', 100)->nullable();
            $t->string('insurance_policy_number', 64)->nullable();
            $t->date('insurance_expiry_date')->nullable();
            $t->date('registration_expiry_date')->nullable();
            $t->date('last_service_date')->nullable();
            $t->date('next_service_date')->nullable();
            $t->unsignedInteger('next_service_odometer')->nullable();
            $t->text('notes')->nullable();
            // Latest known position (denormalised for the live map; history lives in vehicle_locations)
            $t->decimal('last_latitude', 10, 7)->nullable();
            $t->decimal('last_longitude', 10, 7)->nullable();
            $t->decimal('last_speed', 6, 2)->nullable();
            $t->decimal('last_heading', 5, 2)->nullable();
            $t->timestamp('last_located_at')->nullable();
            $t->unsignedBigInteger('last_driver_id')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['company_id', 'registration_number']);
            $t->unique(['company_id', 'vin']);
            $t->index(['company_id', 'status']);
            $t->index(['company_id', 'next_service_date']);
        });

        Schema::create('vehicle_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->string('document_type', 40);
            $t->string('document_number', 64)->nullable();
            $t->string('file_path')->nullable();
            $t->string('original_name')->nullable();
            $t->date('issue_date')->nullable();
            $t->date('expiry_date')->nullable()->index();
            $t->string('status', 20)->default('valid');
            $t->timestamps();
        });

        Schema::create('drivers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('first_name', 80);
            $t->string('last_name', 80);
            $t->string('email')->nullable();
            $t->string('phone', 32);
            $t->string('photo_path')->nullable();
            $t->string('license_number', 64);
            $t->string('license_type', 10); // A, B, C, CE, D …
            $t->date('license_expiry_date');
            $t->date('employment_date')->nullable();
            $t->string('status', 20)->default('active')->index(); // active, inactive, suspended
            $t->string('availability', 20)->default('available'); // available, on_duty, off_duty, on_leave
            $t->string('emergency_contact', 120)->nullable();
            $t->string('emergency_phone', 32)->nullable();
            $t->string('national_id', 64)->nullable();
            $t->string('address')->nullable();
            $t->date('date_of_birth')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['company_id', 'license_number']);
            $t->unique(['company_id', 'user_id']);
            $t->index(['company_id', 'status', 'availability']);
        });

        Schema::table('invitations', function (Blueprint $t) {
            $t->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
        });

        Schema::create('driver_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $t->string('document_type', 40);
            $t->string('document_number', 64)->nullable();
            $t->string('file_path')->nullable();
            $t->string('original_name')->nullable();
            $t->date('issue_date')->nullable();
            $t->date('expiry_date')->nullable()->index();
            $t->string('status', 20)->default('valid');
            $t->timestamps();
        });

        // Availability history (time off, status changes)
        Schema::create('driver_availabilities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20);
            $t->timestamp('starts_at')->useCurrent();
            $t->timestamp('ends_at')->nullable();
            $t->string('reason')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['driver_id', 'starts_at']);
        });

        // Working shifts (on duty → off duty) for working-hours tracking
        Schema::create('driver_shifts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('started_at')->useCurrent();
            $t->timestamp('ended_at')->nullable();
            $t->unsignedInteger('duration_minutes')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'started_at']);
            $t->index(['driver_id', 'ended_at']);
        });

        Schema::create('vehicle_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $t->timestamp('assigned_at')->useCurrent();
            $t->timestamp('unassigned_at')->nullable();
            $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('unassigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            // Set only while active → DB-level guarantee of one active assignment per vehicle / per driver.
            $t->unsignedBigInteger('active_vehicle_id')->nullable()->unique();
            $t->unsignedBigInteger('active_driver_id')->nullable()->unique();
            $t->timestamps();
            $t->index(['vehicle_id', 'assigned_at']);
            $t->index(['driver_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('invitations', fn (Blueprint $t) => $t->dropForeign(['driver_id']));
        foreach (['vehicle_assignments', 'driver_shifts', 'driver_availabilities', 'driver_documents', 'drivers', 'vehicle_documents', 'vehicles', 'vehicle_types'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
