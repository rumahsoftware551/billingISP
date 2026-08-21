<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('source', 40)->default('legacy')->after('method');
            $table->string('idempotency_key', 128)->nullable()->after('reference');
            $table->string('request_fingerprint', 64)->nullable()->after('idempotency_key');
            $table->unique(
                ['tenant_id', 'source', 'idempotency_key'],
                'payments_tenant_source_idempotency_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_tenant_source_idempotency_unique');
            $table->dropColumn(['source', 'idempotency_key', 'request_fingerprint']);
        });
    }
};
