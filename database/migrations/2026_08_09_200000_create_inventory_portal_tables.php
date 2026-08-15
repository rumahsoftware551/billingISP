<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('technician_id')->nullable()->constrained('technicians')->nullOnDelete();
            $table->string('code', 60);
            $table->string('name', 160);
            $table->string('location_type', 30)->default('warehouse')->index(); // warehouse, technician, transit, repair
            $table->text('address')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'technician_id']);
            $table->index(['tenant_id', 'location_type', 'active']);
        });

        Schema::create('inventory_portal_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('inventory_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('technicians')->nullOnDelete();
            $table->string('name', 160);
            $table->string('email', 190);
            $table->string('password');
            $table->string('role', 30)->default('warehouse_staff')->index(); // warehouse_manager, warehouse_staff, technician, auditor
            $table->string('status', 30)->default('active')->index();
            $table->boolean('must_change_password')->default(false);
            $table->timestampTz('last_login_at')->nullable();
            $table->string('last_login_ip', 64)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'role', 'status']);
        });

        Schema::create('inventory_login_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('inventory_portal_account_id')->nullable()->constrained('inventory_portal_accounts')->nullOnDelete();
            $table->string('event', 40)->index();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('inventory_skus', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('sku', 80);
            $table->string('name', 180);
            $table->string('category', 80)->index();
            $table->string('brand', 100)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('uom', 30)->default('pcs');
            $table->decimal('minimum_stock', 14, 3)->default(0);
            $table->boolean('serialized')->default(false)->index();
            $table->boolean('track_mac')->default(false);
            $table->boolean('active')->default(true)->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'category', 'active']);
        });

        Schema::create('inventory_suppliers', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('code', 60);
            $table->string('name', 180);
            $table->string('contact_name', 160)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 190)->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number', 80)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('inventory_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('po_number', 60);
            $table->foreignId('supplier_id')->constrained('inventory_suppliers')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_inventory_account_id')->nullable()->constrained('inventory_portal_accounts')->nullOnDelete();
            $table->string('status', 30)->default('draft')->index(); // draft, ordered, partial, received, canceled
            $table->date('ordered_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'po_number']);
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('inventory_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('purchase_order_id')->constrained('inventory_purchase_orders')->cascadeOnDelete();
            $table->foreignId('inventory_sku_id')->constrained('inventory_skus')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->decimal('unit_cost', 16, 2)->default(0);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['purchase_order_id', 'inventory_sku_id']);
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('inventory_sku_id')->constrained('inventory_skus')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->decimal('quantity_reserved', 14, 3)->default(0);
            $table->decimal('average_cost', 16, 2)->default(0);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'inventory_location_id', 'inventory_sku_id'], 'inventory_balances_unique');
            $table->index(['tenant_id', 'inventory_sku_id']);
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('transaction_number', 60);
            $table->string('transaction_type', 40)->index(); // receive, transfer, issue, return, install, retrieve, adjustment, repair, damage, lost
            $table->string('status', 30)->default('posted')->index();
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('inventory_suppliers')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('inventory_purchase_orders')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained('customer_services')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_inventory_account_id')->nullable()->constrained('inventory_portal_accounts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampTz('occurred_at')->useCurrent()->index();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'transaction_number']);
            $table->index(['tenant_id', 'transaction_type', 'occurred_at']);
        });

        Schema::create('inventory_transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('inventory_transaction_id')->constrained('inventory_transactions')->cascadeOnDelete();
            $table->foreignId('inventory_sku_id')->constrained('inventory_skus')->restrictOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['inventory_transaction_id', 'inventory_sku_id']);
        });

        Schema::create('inventory_stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('opname_number', 60);
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('created_by_inventory_account_id')->nullable()->constrained('inventory_portal_accounts')->nullOnDelete();
            $table->string('status', 30)->default('posted')->index();
            $table->timestampTz('counted_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'opname_number']);
        });

        Schema::create('inventory_stock_opname_lines', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('inventory_stock_opname_id')->constrained('inventory_stock_opnames')->cascadeOnDelete();
            $table->foreignId('inventory_sku_id')->constrained('inventory_skus')->restrictOnDelete();
            $table->decimal('system_quantity', 14, 3);
            $table->decimal('counted_quantity', 14, 3);
            $table->decimal('variance_quantity', 14, 3);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('inventory_sku_id')->nullable()->after('tenant_id')->constrained('inventory_skus')->nullOnDelete();
            $table->foreignId('current_location_id')->nullable()->after('inventory_sku_id')->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('current_location_id')->constrained('inventory_suppliers')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->after('supplier_id')->constrained('inventory_purchase_orders')->nullOnDelete();
            $table->string('barcode', 120)->nullable()->after('mac_address');
            $table->string('condition', 30)->default('good')->after('status')->index();
            $table->decimal('acquisition_cost', 16, 2)->default(0)->after('condition');
            $table->timestampTz('installed_at')->nullable()->after('warranty_until');
            $table->timestampTz('retrieved_at')->nullable()->after('installed_at');
            $table->index(['tenant_id', 'inventory_sku_id', 'current_location_id'], 'inventory_items_stock_idx');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('inventory_transaction_id')->nullable()->after('inventory_item_id')->constrained('inventory_transactions')->nullOnDelete();
            $table->foreignId('inventory_sku_id')->nullable()->after('inventory_transaction_id')->constrained('inventory_skus')->nullOnDelete();
            $table->foreignId('from_location_id')->nullable()->after('inventory_sku_id')->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->after('from_location_id')->constrained('inventory_locations')->nullOnDelete();
            $table->decimal('quantity', 14, 3)->default(1)->after('to_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_transaction_id');
            $table->dropConstrainedForeignId('inventory_sku_id');
            $table->dropConstrainedForeignId('from_location_id');
            $table->dropConstrainedForeignId('to_location_id');
            $table->dropColumn('quantity');
        });
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex('inventory_items_stock_idx');
            $table->dropConstrainedForeignId('inventory_sku_id');
            $table->dropConstrainedForeignId('current_location_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('purchase_order_id');
            $table->dropColumn(['barcode','condition','acquisition_cost','installed_at','retrieved_at']);
        });
        foreach ([
            'inventory_stock_opname_lines','inventory_stock_opnames','inventory_transaction_lines','inventory_transactions',
            'inventory_balances','inventory_purchase_order_items','inventory_purchase_orders','inventory_suppliers','inventory_skus',
            'inventory_login_events','inventory_portal_accounts','inventory_locations'
        ] as $table) Schema::dropIfExists($table);
    }
};
