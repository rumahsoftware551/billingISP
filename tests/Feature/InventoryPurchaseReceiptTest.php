<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\InventoryLocation;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventorySku;
use App\Models\InventorySupplier;
use App\Services\InventoryLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryPurchaseReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_receipt_cannot_exceed_locked_remaining_quantity(): void
    {
        $tenant = $this->createTenant();
        $location = InventoryLocation::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'WH-01',
            'name' => 'Gudang Utama',
            'location_type' => 'warehouse',
            'active' => true,
        ]);
        $supplier = InventorySupplier::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'SUP-01',
            'name' => 'Supplier Test',
            'active' => true,
        ]);
        $sku = InventorySku::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'CABLE-01',
            'name' => 'Drop Cable',
            'category' => 'cable',
            'uom' => 'roll',
            'serialized' => false,
            'active' => true,
        ]);
        $purchase = InventoryPurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'po_number' => 'PO-TEST-0001',
            'supplier_id' => $supplier->id,
            'destination_location_id' => $location->id,
            'status' => 'ordered',
            'ordered_at' => '2026-08-21',
            'total_amount' => 500000,
        ]);
        $item = $purchase->items()->create([
            'tenant_id' => $tenant->id,
            'inventory_sku_id' => $sku->id,
            'quantity' => 5,
            'received_quantity' => 0,
            'unit_cost' => 100000,
        ]);

        $ledger = app(InventoryLedgerService::class);
        $ledger->receivePurchaseOrder($purchase, $item->id, 4);

        $this->assertSame(4.0, (float) $item->fresh()->received_quantity);
        $this->assertSame('partial', $purchase->fresh()->status);
        $this->assertSame(4.0, (float) InventoryBalance::query()->firstOrFail()->quantity_on_hand);

        try {
            $ledger->receivePurchaseOrder($purchase->fresh(), $item->id, 2);
            $this->fail('Over-receipt should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $this->assertSame(4.0, (float) $item->fresh()->received_quantity);
        $this->assertSame(4.0, (float) InventoryBalance::query()->firstOrFail()->quantity_on_hand);
    }
}
