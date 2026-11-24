<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->string('maintenance_type', 30);
            $t->string('title', 150);
            $t->text('description')->nullable();
            $t->string('service_provider', 120)->nullable();
            $t->date('scheduled_date')->nullable();
            $t->date('completed_date')->nullable();
            $t->unsignedInteger('odometer')->nullable();
            $t->decimal('cost', 12, 2)->default(0);
            $t->string('status', 20)->default('scheduled'); // scheduled, in_progress, completed, cancelled
            $t->date('next_service_date')->nullable();
            $t->unsignedInteger('next_service_odometer')->nullable();
            $t->string('invoice_path')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'status', 'scheduled_date']);
            $t->index(['vehicle_id', 'completed_date']);
        });

        Schema::create('maintenance_reminders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->string('reminder_type', 30); // service_due, service_overdue, document_expiring, inspection_due
            $t->string('subject_type', 40)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->date('due_date')->nullable();
            $t->unsignedInteger('due_odometer')->nullable();
            $t->string('status', 20)->default('open'); // open, resolved, dismissed
            $t->timestamp('notified_at')->nullable();
            $t->timestamps();
            // One reminder per subject/type/due date — the scheduler is idempotent.
            $t->unique(['vehicle_id', 'reminder_type', 'subject_type', 'subject_id', 'due_date'], 'maint_reminder_unique');
            $t->index(['company_id', 'status']);
        });

        Schema::create('fuel_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $t->string('fuel_type', 20);
            $t->decimal('quantity', 10, 2); // litres
            $t->decimal('unit_price', 12, 3);
            $t->decimal('total_cost', 12, 2); // computed by the backend
            $t->unsignedInteger('odometer');
            $t->boolean('full_tank')->default(true);
            $t->string('station_name', 120)->nullable();
            $t->string('receipt_path')->nullable();
            $t->timestamp('recorded_at')->useCurrent();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'recorded_at']);
            $t->index(['vehicle_id', 'odometer']);
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category', 30);
            $t->string('description');
            $t->decimal('amount', 12, 2);
            $t->date('expense_date');
            $t->string('receipt_path')->nullable();
            $t->string('status', 20)->default('pending'); // pending, approved, rejected
            $t->string('source_type', 30)->nullable(); // maintenance_record, fuel_record (auto-created)
            $t->unsignedBigInteger('source_id')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('rejection_reason')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['company_id', 'expense_date']);
            $t->index(['company_id', 'status']);
            $t->unique(['source_type', 'source_id']);
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('invoice_number', 32);
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('tax_rate', 5, 2)->default(0);
            $t->decimal('tax_amount', 12, 2)->default(0);
            $t->decimal('total_amount', 12, 2)->default(0);
            $t->decimal('amount_paid', 12, 2)->default(0);
            $t->char('currency', 3);
            $t->string('status', 20)->default('draft'); // draft, issued, partially_paid, paid, void
            $t->date('due_date')->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'invoice_number']);
            $t->index(['company_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $t->string('description');
            $t->decimal('quantity', 10, 2)->default(1);
            $t->decimal('unit_price', 12, 2);
            $t->decimal('total_price', 12, 2);
            $t->timestamps();
            // A delivery can be billed only once.
            $t->unique('delivery_id');
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->string('payment_method', 30); // cash, bank_transfer, card, cod, cheque, other
            $t->string('reference', 120)->nullable();
            $t->string('status', 20)->default('completed'); // completed, refunded
            $t->timestamp('paid_at')->useCurrent();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        foreach (['payments', 'invoice_items', 'invoices', 'expenses', 'fuel_records', 'maintenance_reminders', 'maintenance_records'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
