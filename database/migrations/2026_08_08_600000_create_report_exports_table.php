<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('report_type', 50)->index();
            $table->string('format', 20)->default('csv');
            $table->jsonb('filters')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('exported_at')->index();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'report_type', 'exported_at'], 'report_exports_tenant_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
