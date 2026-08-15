<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_brandings', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->unique();
            $table->string('app_name')->default('Jaringanku');
            $table->string('company_name')->nullable();
            $table->string('portal_title')->nullable();
            $table->string('primary_color', 20)->default('#0f6cbd');
            $table->string('accent_color', 20)->default('#16a34a');
            $table->string('logo_path')->nullable();
            $table->string('login_logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('invoice_logo_path')->nullable();
            $table->string('support_phone', 60)->nullable();
            $table->string('support_email', 190)->nullable();
            $table->text('address')->nullable();
            $table->string('footer_text', 255)->nullable();
            $table->boolean('show_powered_by')->default(true);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('custom_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('code', 80);
            $table->string('name', 160);
            $table->string('type', 40)->default('custom')->index();
            $table->string('bank_name', 120)->nullable();
            $table->string('account_name', 160)->nullable();
            $table->string('account_number', 160)->nullable();
            $table->text('instructions')->nullable();
            $table->string('qr_image_path')->nullable();
            $table->string('admin_fee_type', 20)->default('none');
            $table->unsignedBigInteger('admin_fee_value')->default(0);
            $table->unsignedBigInteger('minimum_amount')->default(0);
            $table->unsignedBigInteger('maximum_amount')->nullable();
            $table->boolean('customer_visible')->default(true);
            $table->boolean('partner_visible')->default(true);
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(100);
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('manual_payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_payment_method_id')->constrained('custom_payment_methods')->cascadeOnDelete();
            $table->foreignId('customer_portal_account_id')->nullable()->constrained('customer_portal_accounts')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('payer_name', 160)->nullable();
            $table->string('reference', 160)->nullable();
            $table->string('proof_path');
            $table->string('status', 30)->default('pending')->index();
            $table->text('customer_note')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->string('description', 255)->nullable();
        });

        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->string('status', 30)->default('active')->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 30)->default('active')->index();
            $table->timestampTz('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'last_login_at', 'last_login_ip']);
        });
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('description');
        });
        Schema::dropIfExists('manual_payment_proofs');
        Schema::dropIfExists('custom_payment_methods');
        Schema::dropIfExists('tenant_brandings');
    }
};
