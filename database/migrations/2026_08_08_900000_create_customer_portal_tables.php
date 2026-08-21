<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_portal_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->string('email', 190)->nullable();
            $table->string('password');
            $table->string('status', 30)->default('active')->index();
            $table->boolean('must_change_password')->default(true);
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->string('last_login_ip', 64)->nullable();
            $table->timestampTz('portal_enabled_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'status']);
        });

        DB::statement('CREATE UNIQUE INDEX customer_portal_accounts_tenant_email_lower_unique ON customer_portal_accounts (tenant_id, lower(email)) WHERE email IS NOT NULL');

        Schema::create('customer_portal_login_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('tenant_id')->index();
            $table->foreignId('customer_portal_account_id')->nullable()->constrained('customer_portal_accounts')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('event', 50)->index();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_portal_login_events');
        Schema::dropIfExists('customer_portal_accounts');
    }
};
