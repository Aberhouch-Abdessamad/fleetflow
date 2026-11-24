<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Machine clients (e-commerce, WMS). Authenticate with Sanctum tokens owned by the client.
        Schema::create('api_clients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('type', 20)->default('ecommerce'); // ecommerce, warehouse, custom
            $t->json('scopes');
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_used_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('webhook_endpoints', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('api_client_id')->nullable()->constrained()->nullOnDelete();
            $t->string('url');
            $t->text('secret'); // encrypted cast
            $t->json('events');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $t->uuid('event_id')->unique();
            $t->string('event', 60);
            $t->json('payload');
            $t->string('status', 20)->default('pending'); // pending, delivered, failed
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->unsignedSmallInteger('response_status')->nullable();
            $t->text('response_body')->nullable();
            $t->timestamp('next_attempt_at')->nullable()->index();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'created_at']);
        });

        Schema::create('integration_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('api_client_id')->constrained()->cascadeOnDelete();
            $t->string('idempotency_key', 120);
            $t->string('endpoint', 120);
            $t->char('request_hash', 64);
            $t->unsignedSmallInteger('response_status')->nullable();
            $t->json('response_body')->nullable();
            $t->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->unique(['api_client_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        foreach (['integration_requests', 'webhook_deliveries', 'webhook_endpoints', 'api_clients'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
