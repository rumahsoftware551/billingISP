<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('status', 30)->default('active')->index();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('area_name', 160)->nullable();
            $table->jsonb('payout_account')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('partner_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('email');
            $table->string('password');
            $table->string('role', 30)->default('owner')->index();
            $table->string('status', 30)->default('active')->index();
            $table->boolean('must_change_password')->default(true);
            $table->timestampTz('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'partner_id', 'status']);
        });

        Schema::create('partner_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 40)->index(); // payment_percent, payment_fixed, activation_fixed, active_customer_fixed
            $table->unsignedBigInteger('value'); // basis points for percent, rupiah for fixed
            $table->boolean('active')->default(true)->index();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'partner_id', 'active']);
        });

        Schema::create('partner_commission_entries', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('partner_commission_rule_id')->nullable()->constrained('partner_commission_rules')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->cascadeOnDelete();
            $table->string('entry_type', 40)->index();
            $table->unsignedBigInteger('basis_amount')->default(0);
            $table->unsignedBigInteger('amount');
            $table->string('status', 30)->default('available')->index();
            $table->string('idempotency_key', 190);
            $table->timestampTz('earned_at')->index();
            $table->timestampTz('paid_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'partner_id', 'status', 'earned_at']);
        });

        Schema::create('partner_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('partner_account_id')->nullable()->constrained('partner_accounts')->nullOnDelete();
            $table->string('withdrawal_number', 60);
            $table->unsignedBigInteger('amount');
            $table->string('status', 30)->default('requested')->index();
            $table->jsonb('payout_account')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('requested_at')->index();
            $table->timestampTz('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'withdrawal_number']);
        });

        Schema::create('partner_login_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('tenant_id')->index();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignId('partner_account_id')->nullable()->constrained('partner_accounts')->nullOnDelete();
            $table->string('event', 30)->index();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('tenant_id')->constrained('partners')->nullOnDelete();
            $table->foreignId('created_by_partner_account_id')->nullable()->after('partner_id')->constrained('partner_accounts')->nullOnDelete();
            $table->index(['tenant_id', 'partner_id', 'status']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('customer_id')->constrained('partners')->nullOnDelete();
            $table->foreignId('partner_account_id')->nullable()->after('partner_id')->constrained('partner_accounts')->nullOnDelete();
            $table->index(['tenant_id', 'partner_id', 'paid_at']);
        });
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('created_by_partner_account_id')->nullable()->after('created_by_portal_account_id')->constrained('partner_accounts')->nullOnDelete();
        });
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->foreignId('partner_account_id')->nullable()->after('customer_portal_account_id')->constrained('partner_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', fn (Blueprint $table) => $table->dropConstrainedForeignId('partner_account_id'));
        Schema::table('support_tickets', fn (Blueprint $table) => $table->dropConstrainedForeignId('created_by_partner_account_id'));
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_account_id');
            $table->dropConstrainedForeignId('partner_id');
        });
        Schema::table('customers', function (Blueprint $table) { $table->dropConstrainedForeignId('created_by_partner_account_id'); $table->dropConstrainedForeignId('partner_id'); });
        foreach (['partner_login_events','partner_withdrawals','partner_commission_entries','partner_commission_rules','partner_accounts','partners'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
