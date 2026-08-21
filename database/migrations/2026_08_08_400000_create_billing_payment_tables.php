<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('name', 120)->default('Default Billing');
            $table->unsignedTinyInteger('invoice_day')->default(1);
            $table->unsignedTinyInteger('due_day')->default(10);
            $table->unsignedTinyInteger('grace_days')->default(3);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained('customer_services')->nullOnDelete();
            $table->string('invoice_number', 64);
            $table->string('billing_key', 120);
            $table->date('period_start')->index();
            $table->date('period_end');
            $table->date('issued_at')->index();
            $table->date('due_at')->index();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedBigInteger('balance_due')->default(0);
            $table->string('status', 30)->default('unpaid')->index();
            $table->timestampTz('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'invoice_number']);
            $table->unique(['tenant_id', 'billing_key']);
            $table->index(['tenant_id', 'customer_id', 'status']);
            $table->index(['tenant_id', 'due_at', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description', 255);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('amount')->default(0);
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('payment_number', 64);
            $table->unsignedBigInteger('amount');
            $table->string('method', 30)->default('cash')->index();
            $table->string('reference', 160)->nullable();
            $table->timestampTz('paid_at')->index();
            $table->string('status', 30)->default('posted')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'payment_number']);
            $table->index(['tenant_id', 'customer_id', 'paid_at']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['payment_id', 'invoice_id']);
        });

        Schema::create('billing_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('run_key', 80);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 30)->default('running')->index();
            $table->unsignedInteger('eligible_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->jsonb('errors')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'run_key']);
        });
    }

    public function down(): void
    {
        foreach (['billing_runs', 'payment_allocations', 'payments', 'invoice_items', 'invoices', 'billing_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
