<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internet_plans', function (Blueprint $table) {
            $table->unsignedInteger('acct_interim_interval')->default(300);
        });

        Schema::table('customer_services', function (Blueprint $table) {
            $table->timestampTz('last_coa_at')->nullable();
            $table->timestampTz('last_disconnect_at')->nullable();
        });

        Schema::table('radacct', function (Blueprint $table) {
            $table->index(['username', 'acctstoptime'], 'radacct_username_stop_idx');
            $table->index(['acctstoptime', 'acctupdatetime'], 'radacct_online_update_idx');
            $table->index('framedipaddress', 'radacct_framed_ip_idx');
        });

        Schema::create('radius_action_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('customer_service_id')->nullable()->constrained('customer_services')->nullOnDelete();
            $table->unsignedBigInteger('radacctid')->nullable()->index();
            $table->foreignId('network_nas_id')->nullable()->constrained('network_nas')->nullOnDelete();
            $table->string('action', 30)->index();
            $table->string('target', 255)->nullable();
            $table->jsonb('request_payload')->nullable();
            $table->string('response_code', 60)->nullable()->index();
            $table->boolean('success')->default(false)->index();
            $table->text('output')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at'], 'radius_action_tenant_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radius_action_logs');

        Schema::table('radacct', function (Blueprint $table) {
            $table->dropIndex('radacct_username_stop_idx');
            $table->dropIndex('radacct_online_update_idx');
            $table->dropIndex('radacct_framed_ip_idx');
        });

        Schema::table('customer_services', function (Blueprint $table) {
            $table->dropColumn(['last_coa_at', 'last_disconnect_at']);
        });

        Schema::table('internet_plans', function (Blueprint $table) {
            $table->dropColumn('acct_interim_interval');
        });
    }
};
