<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // customer portal account
            $t->string('name', 150);
            $t->string('email')->nullable();
            $t->string('phone', 32)->nullable();
            $t->string('customer_type', 20)->default('business'); // business, individual
            $t->string('tax_number', 64)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['company_id', 'name']);
            $t->index(['company_id', 'email']);
        });

        Schema::create('customer_addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('label', 60)->nullable();
            $t->string('address_type', 20)->default('delivery'); // pickup, delivery, billing
            $t->string('address');
            $t->string('city', 120);
            $t->string('postal_code', 20)->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('contact_name', 120)->nullable();
            $t->string('contact_phone', 32)->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        Schema::create('routes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('reference_number', 32);
            $t->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $t->date('route_date')->index();
            $t->string('start_location')->nullable();
            $t->decimal('start_latitude', 10, 7)->nullable();
            $t->decimal('start_longitude', 10, 7)->nullable();
            $t->string('end_location')->nullable();
            $t->decimal('end_latitude', 10, 7)->nullable();
            $t->decimal('end_longitude', 10, 7)->nullable();
            $t->decimal('planned_distance', 10, 2)->nullable(); // km
            $t->decimal('actual_distance', 10, 2)->nullable();
            $t->unsignedInteger('estimated_duration')->nullable(); // minutes
            $t->unsignedInteger('actual_duration')->nullable();
            $t->string('distance_source', 20)->nullable(); // provider name or 'straight_line'
            $t->json('geometry')->nullable(); // provider polyline/geojson
            $t->string('status', 20)->default('planned')->index(); // planned, in_progress, completed, cancelled
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'reference_number']);
        });

        Schema::create('deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reference_number', 32);
            $t->string('external_source', 40)->nullable();
            $t->string('external_reference', 120)->nullable();
            $t->string('tracking_token', 64)->unique();
            $t->string('pickup_address');
            $t->decimal('pickup_latitude', 10, 7)->nullable();
            $t->decimal('pickup_longitude', 10, 7)->nullable();
            $t->string('pickup_contact_name', 120)->nullable();
            $t->string('pickup_contact_phone', 32)->nullable();
            $t->string('delivery_address');
            $t->decimal('delivery_latitude', 10, 7)->nullable();
            $t->decimal('delivery_longitude', 10, 7)->nullable();
            $t->string('delivery_city', 120)->nullable()->index();
            $t->string('recipient_name', 120);
            $t->string('recipient_phone', 32)->nullable();
            $t->string('recipient_email')->nullable();
            $t->string('description')->nullable();
            $t->timestamp('scheduled_pickup_at')->nullable();
            $t->timestamp('scheduled_delivery_at')->nullable()->index();
            $t->timestamp('actual_pickup_at')->nullable();
            $t->timestamp('actual_delivery_at')->nullable();
            $t->foreignId('assigned_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $t->foreignId('assigned_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $t->foreignId('route_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->string('priority', 10)->default('normal'); // low, normal, high, urgent
            $t->string('status', 20)->default('pending');
            $t->decimal('total_weight', 10, 2)->default(0); // kg, computed from items
            $t->decimal('delivery_fee', 12, 2)->default(0);
            $t->decimal('cod_amount', 12, 2)->default(0);
            $t->decimal('cod_collected_amount', 12, 2)->nullable();
            $t->timestamp('cod_collected_at')->nullable();
            $t->boolean('requires_proof')->default(true);
            $t->string('otp_hash')->nullable();
            $t->text('delivery_notes')->nullable();
            $t->string('failure_reason')->nullable();
            $t->string('cancellation_reason')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->unsignedTinyInteger('customer_rating')->nullable();
            $t->string('rating_comment', 500)->nullable();
            $t->decimal('distance_km', 10, 2)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['company_id', 'reference_number']);
            $t->unique(['company_id', 'external_source', 'external_reference']);
            $t->index(['company_id', 'status']);
            $t->index(['company_id', 'created_at']);
            $t->index(['assigned_driver_id', 'status']);
            $t->index(['assigned_vehicle_id', 'status']);
        });

        Schema::create('delivery_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $t->string('description');
            $t->unsignedInteger('quantity')->default(1);
            $t->decimal('weight', 10, 2)->default(0); // kg per unit
            $t->string('dimensions', 60)->nullable();
            $t->string('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('delivery_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $t->string('previous_status', 20)->nullable();
            $t->string('new_status', 20);
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('source', 20)->default('user'); // user, driver, system, integration
            $t->string('notes', 500)->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->timestamp('changed_at')->useCurrent()->index();
        });

        // Every (re)assignment of a delivery, preserving history.
        Schema::create('delivery_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $t->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('pending'); // pending, accepted, rejected, superseded, cancelled, completed
            $t->timestamp('responded_at')->nullable();
            $t->string('response_reason')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'status']);
            $t->index(['delivery_id', 'created_at']);
        });

        Schema::create('delivery_proofs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $t->string('proof_type', 20); // photo, signature, otp, confirmation
            $t->string('file_path')->nullable();
            $t->string('recipient_name', 120)->nullable();
            $t->string('recipient_signature_path')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('notes', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('route_stops', function (Blueprint $t) {
            $t->id();
            $t->foreignId('route_id')->constrained()->cascadeOnDelete();
            $t->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('stop_order');
            $t->string('stop_type', 10)->default('delivery'); // pickup, delivery
            $t->string('address');
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('priority', 10)->default('normal');
            $t->timestamp('estimated_arrival')->nullable();
            $t->timestamp('actual_arrival')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->string('status', 20)->default('pending'); // pending, arrived, completed, skipped
            $t->string('notes')->nullable();
            $t->timestamps();
            $t->index(['route_id', 'stop_order']);
        });

        Schema::create('route_optimization_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('route_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 30);
            $t->json('original_sequence');
            $t->json('optimized_sequence');
            $t->decimal('distance_before', 10, 2)->nullable();
            $t->decimal('distance_after', 10, 2)->nullable();
            $t->unsignedInteger('duration_before')->nullable();
            $t->unsignedInteger('duration_after')->nullable();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('optimized_at')->useCurrent();
        });

        Schema::create('vehicle_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $t->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->decimal('speed', 6, 2)->nullable(); // km/h
            $t->decimal('heading', 5, 2)->nullable();
            $t->decimal('accuracy', 8, 2)->nullable(); // metres
            $t->timestamp('recorded_at')->useCurrent();
            $t->timestamp('received_at')->useCurrent();
            // Duplicate events (same driver + device timestamp) are rejected at DB level.
            $t->unique(['driver_id', 'recorded_at']);
            $t->index(['company_id', 'vehicle_id', 'recorded_at']);
            $t->index(['company_id', 'recorded_at']);
        });

        Schema::create('geofences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('location_type', 30)->default('depot'); // depot, warehouse, customer, restricted
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->unsignedInteger('radius'); // metres
            $t->string('status', 20)->default('active');
            $t->timestamps();
        });

        Schema::create('geofence_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('geofence_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->string('event_type', 10); // enter, exit
            $t->timestamp('occurred_at')->useCurrent();
            $t->json('metadata')->nullable();
            $t->index(['vehicle_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        foreach (['geofence_events', 'geofences', 'vehicle_locations', 'route_optimization_logs', 'route_stops', 'delivery_proofs', 'delivery_assignments', 'delivery_status_histories', 'delivery_items', 'deliveries', 'routes', 'customer_addresses', 'customers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
