<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('registration_number', 64)->nullable();
            $table->string('contact_email');
            $table->string('phone', 32)->nullable();
            $table->char('country', 2);
            $table->string('city', 120);
            $table->string('address')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // A user's membership in a company. Roles inside the company are held by
        // Spatie (teams mode, company_id = team id); this table answers "may this
        // user act inside this company at all?" and carries membership state.
        Schema::create('company_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('job_title')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_company_id')->nullable()->after('locale')
                ->constrained('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_company_id');
        });
        Schema::dropIfExists('company_users');
        Schema::dropIfExists('companies');
    }
};
