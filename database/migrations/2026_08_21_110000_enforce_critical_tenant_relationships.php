<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string,string> */
    private array $tenantKeys = [
        'roles' => 'roles_tenant_id_id_unique',
        'routers' => 'routers_tenant_id_id_unique',
        'network_nas' => 'network_nas_tenant_id_id_unique',
        'internet_plans' => 'internet_plans_tenant_id_id_unique',
        'ip_pools' => 'ip_pools_tenant_id_id_unique',
        'customers' => 'customers_tenant_id_id_unique',
        'customer_services' => 'customer_services_tenant_id_id_unique',
        'invoices' => 'invoices_tenant_id_id_unique',
        'payments' => 'payments_tenant_id_id_unique',
        'technicians' => 'technicians_tenant_id_id_unique',
        'inventory_locations' => 'inventory_locations_tenant_id_id_unique',
        'inventory_portal_accounts' => 'inventory_portal_accounts_tenant_id_id_unique',
        'inventory_skus' => 'inventory_skus_tenant_id_id_unique',
        'inventory_suppliers' => 'inventory_suppliers_tenant_id_id_unique',
        'inventory_purchase_orders' => 'inventory_purchase_orders_tenant_id_id_unique',
    ];

    /** @var array<int,array{0:string,1:string,2:string,3:string}> */
    private array $tenantRelations = [
        ['tenant_memberships', 'role_id', 'roles', 'tenant_memberships_tenant_role_fk'],
        ['network_nas', 'router_id', 'routers', 'network_nas_tenant_router_fk'],
        ['customer_addresses', 'customer_id', 'customers', 'customer_addresses_tenant_customer_fk'],
        ['customer_contacts', 'customer_id', 'customers', 'customer_contacts_tenant_customer_fk'],
        ['customer_services', 'customer_id', 'customers', 'customer_services_tenant_customer_fk'],
        ['customer_services', 'internet_plan_id', 'internet_plans', 'customer_services_tenant_plan_fk'],
        ['customer_services', 'router_id', 'routers', 'customer_services_tenant_router_fk'],
        ['customer_services', 'network_nas_id', 'network_nas', 'customer_services_tenant_nas_fk'],
        ['customer_services', 'ip_pool_id', 'ip_pools', 'customer_services_tenant_pool_fk'],
        ['service_status_histories', 'customer_service_id', 'customer_services', 'service_status_histories_tenant_service_fk'],
        ['invoices', 'customer_id', 'customers', 'invoices_tenant_customer_fk'],
        ['invoices', 'customer_service_id', 'customer_services', 'invoices_tenant_service_fk'],
        ['invoice_items', 'invoice_id', 'invoices', 'invoice_items_tenant_invoice_fk'],
        ['payments', 'customer_id', 'customers', 'payments_tenant_customer_fk'],
        ['payment_allocations', 'payment_id', 'payments', 'payment_allocations_tenant_payment_fk'],
        ['payment_allocations', 'invoice_id', 'invoices', 'payment_allocations_tenant_invoice_fk'],
        ['inventory_locations', 'technician_id', 'technicians', 'inventory_locations_tenant_technician_fk'],
        ['inventory_portal_accounts', 'inventory_location_id', 'inventory_locations', 'inventory_accounts_tenant_location_fk'],
        ['inventory_portal_accounts', 'technician_id', 'technicians', 'inventory_accounts_tenant_technician_fk'],
        ['inventory_purchase_orders', 'supplier_id', 'inventory_suppliers', 'inventory_purchase_orders_tenant_supplier_fk'],
        ['inventory_purchase_orders', 'destination_location_id', 'inventory_locations', 'inventory_purchase_orders_tenant_destination_fk'],
        ['inventory_purchase_orders', 'created_by_inventory_account_id', 'inventory_portal_accounts', 'inventory_purchase_orders_tenant_account_fk'],
        ['inventory_purchase_order_items', 'purchase_order_id', 'inventory_purchase_orders', 'inventory_po_items_tenant_purchase_fk'],
        ['inventory_purchase_order_items', 'inventory_sku_id', 'inventory_skus', 'inventory_po_items_tenant_sku_fk'],
        ['inventory_balances', 'inventory_location_id', 'inventory_locations', 'inventory_balances_tenant_location_fk'],
        ['inventory_balances', 'inventory_sku_id', 'inventory_skus', 'inventory_balances_tenant_sku_fk'],
    ];

    public function up(): void
    {
        foreach ($this->tenantKeys as $tableName => $constraintName) {
            Schema::table($tableName, function (Blueprint $table) use ($constraintName): void {
                $table->unique(['tenant_id', 'id'], $constraintName);
            });
        }

        foreach ($this->tenantRelations as [$tableName, $column, $targetTable, $constraintName]) {
            Schema::table($tableName, function (Blueprint $table) use ($column, $targetTable, $constraintName): void {
                $table->foreign(['tenant_id', $column], $constraintName)
                    ->references(['tenant_id', 'id'])
                    ->on($targetTable);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tenantRelations) as [$tableName, , , $constraintName]) {
            Schema::table($tableName, function (Blueprint $table) use ($constraintName): void {
                $table->dropForeign($constraintName);
            });
        }

        foreach (array_reverse($this->tenantKeys, true) as $tableName => $constraintName) {
            Schema::table($tableName, function (Blueprint $table) use ($constraintName): void {
                $table->dropUnique($constraintName);
            });
        }
    }
};
