<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code', 50)->unique();
            $t->text('description')->nullable();
            $t->decimal('price_monthly', 12, 2)->default(0);
            $t->char('currency', 3)->default('USD');
            $t->unsignedSmallInteger('trial_days')->default(14);
            // null = unlimited
            $t->unsignedInteger('max_vehicles')->nullable();
            $t->unsignedInteger('max_drivers')->nullable();
            $t->unsignedInteger('max_users')->nullable();
            $t->unsignedInteger('max_monthly_deliveries')->nullable();
            $t->json('features')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_default')->default(false);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('company_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $t->string('status', 20)->index(); // trialing, active, expired, cancelled
            $t->timestamp('trial_ends_at')->nullable();
            $t->timestamp('starts_at')->useCurrent();
            $t->timestamp('ends_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('notes')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'status']);
        });

        Schema::create('company_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('key', 100);
            $t->json('value')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'key']);
        });

        Schema::create('platform_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 100)->unique();
            $t->json('value')->nullable();
            $t->timestamps();
        });

        Schema::create('invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('email');
            $t->string('role', 50);
            $t->string('token_hash', 64)->unique();
            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('driver_id')->nullable(); // link to a driver profile (FK added later)
            $t->timestamp('expires_at')->useCurrent();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'email']);
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->foreignId('company_id')->nullable()->index();
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'invitations', 'platform_settings', 'company_settings', 'company_subscriptions', 'subscription_plans'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
