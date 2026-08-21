<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventorySku;
use App\Models\InventorySupplier;
use App\Services\InventoryLedgerService;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryPurchaseController extends Controller
{
    private function manager(Request $request): object
    {
        $account = $request->attributes->get('inventory_account');
        abort_unless($account?->isManager(), 403);

        return $account;
    }

    public function store(Request $request, TenantSequenceService $sequences): RedirectResponse
    {
        $account = $this->manager($request);
        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'destination_location_id' => ['required', 'integer'],
            'inventory_sku_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        InventorySupplier::findOrFail($data['supplier_id']);
        InventoryLocation::findOrFail($data['destination_location_id']);
        InventorySku::findOrFail($data['inventory_sku_id']);

        DB::transaction(function () use ($data, $account, $sequences): void {
            $purchase = InventoryPurchaseOrder::create([
                'po_number' => $sequences->next(app(CurrentTenant::class)->id(), 'inventory_po', 'PO-', 7),
                'supplier_id' => $data['supplier_id'],
                'destination_location_id' => $data['destination_location_id'],
                'created_by_inventory_account_id' => $account->id,
                'status' => 'ordered',
                'ordered_at' => now()->toDateString(),
                'expected_at' => $data['expected_at'] ?? null,
                'total_amount' => (float) $data['quantity'] * (float) $data['unit_cost'],
                'notes' => $data['notes'] ?? null,
            ]);
            $purchase->items()->create([
                'inventory_sku_id' => $data['inventory_sku_id'],
                'quantity' => $data['quantity'],
                'received_quantity' => 0,
                'unit_cost' => $data['unit_cost'],
            ]);
        }, 3);

        return back()->with('success', 'Purchase Order dibuat.');
    }

    public function receive(
        Request $request,
        InventoryPurchaseOrder $purchase,
        InventoryLedgerService $ledger,
    ): RedirectResponse {
        $account = $this->manager($request);
        abort_unless((string) $purchase->tenant_id === app(CurrentTenant::class)->id(), 404);

        $data = $request->validate([
            'purchase_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'serials' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Used only to parse serialized input. The ledger re-fetches and locks both
        // purchase order and item before checking the remaining quantity.
        $previewItem = $purchase->items()->whereKey($data['purchase_item_id'])->firstOrFail();
        $sku = $previewItem->sku()->firstOrFail();
        $assets = [];
        if ($sku->serialized) {
            foreach (preg_split('/\r\n|\r|\n/', trim((string) ($data['serials'] ?? ''))) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                [$serial, $mac, $barcode] = array_pad(array_map('trim', explode(',', $line, 3)), 3, '');
                $assets[] = ['serial_number' => $serial, 'mac_address' => $mac, 'barcode' => $barcode];
            }
        }

        $ledger->receivePurchaseOrder(
            $purchase,
            (int) $data['purchase_item_id'],
            (float) $data['quantity'],
            $assets,
            $account->id,
            null,
            $data['notes'] ?? null,
        );

        return back()->with('success', 'Penerimaan PO berhasil.');
    }
}
