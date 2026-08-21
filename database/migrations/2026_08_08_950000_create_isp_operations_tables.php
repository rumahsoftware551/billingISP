<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technicians', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->jsonb('skills')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status', 'name']);
        });

        Schema::create('network_nodes', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('parent_node_id')->nullable()->constrained('network_nodes')->nullOnDelete();
            $table->string('code', 60);
            $table->string('name', 160);
            $table->string('node_type', 30)->index(); // pop, olt, odc, odp, splitter, other
            $table->string('status', 30)->default('active')->index();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('capacity_ports')->nullable();
            $table->unsignedSmallInteger('used_ports')->default(0);
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'node_type', 'status']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('ticket_number', 40);
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained('customer_services')->nullOnDelete();
            $table->foreignId('assigned_technician_id')->nullable()->constrained('technicians')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_portal_account_id')->nullable()->constrained('customer_portal_accounts')->nullOnDelete();
            $table->string('source', 30)->default('admin')->index();
            $table->string('category', 40)->default('technical')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 30)->default('open')->index();
            $table->string('subject', 200);
            $table->text('description');
            $table->timestampTz('opened_at')->nullable()->index();
            $table->timestampTz('first_response_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'ticket_number']);
            $table->index(['tenant_id', 'status', 'priority']);
            $table->index(['tenant_id', 'customer_id', 'created_at']);
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_portal_account_id')->nullable()->constrained('customer_portal_accounts')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false)->index();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['support_ticket_id', 'created_at']);
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('work_order_number', 40);
            $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained('customer_services')->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('technicians')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->default('repair')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 30)->default('planned')->index();
            $table->timestampTz('scheduled_at')->nullable()->index();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('instructions')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'work_order_number']);
            $table->index(['tenant_id', 'status', 'scheduled_at']);
            $table->index(['tenant_id', 'technician_id', 'status']);
        });

        Schema::create('installation_jobs', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('installation_number', 40);
            $table->foreignId('customer_service_id')->constrained('customer_services')->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('technicians')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->string('status', 30)->default('planned')->index();
            $table->timestampTz('scheduled_at')->nullable()->index();
            $table->timestampTz('arrived_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('installation_notes')->nullable();
            $table->jsonb('activation_data')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'installation_number']);
            $table->index(['tenant_id', 'status', 'scheduled_at']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('asset_code', 60);
            $table->string('category', 50)->index();
            $table->string('brand', 100)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_number', 160)->nullable();
            $table->string('mac_address', 40)->nullable();
            $table->string('status', 30)->default('available')->index();
            $table->foreignId('assigned_customer_service_id')->nullable()->constrained('customer_services')->nullOnDelete();
            $table->foreignId('assigned_technician_id')->nullable()->constrained('technicians')->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'asset_code']);
            $table->index(['tenant_id', 'category', 'status']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('movement_type', 40)->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->foreignId('customer_service_id')->nullable()->constrained('customer_services')->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('technicians')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_type', 80)->nullable();
            $table->string('reference_id', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('service_network_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('customer_service_id')->unique()->constrained('customer_services')->cascadeOnDelete();
            $table->foreignId('network_node_id')->constrained('network_nodes')->restrictOnDelete();
            $table->string('port_number', 40)->nullable();
            $table->unsignedInteger('cable_length_m')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'network_node_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'service_network_assignments',
            'inventory_movements',
            'inventory_items',
            'installation_jobs',
            'work_orders',
            'ticket_comments',
            'support_tickets',
            'network_nodes',
            'technicians',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
