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
class InventoryPurchaseController extends Controller {
    private function manager(Request $r):object { $a=$r->attributes->get('inventory_account'); abort_unless($a?->isManager(),403); return $a; }
    public function store(Request $r,TenantSequenceService $seq):RedirectResponse { $a=$this->manager($r); $d=$r->validate(['supplier_id'=>'required|integer','destination_location_id'=>'required|integer','inventory_sku_id'=>'required|integer','quantity'=>'required|numeric|min:0.001','unit_cost'=>'required|numeric|min:0','expected_at'=>'nullable|date','notes'=>'nullable|string|max:2000']); InventorySupplier::findOrFail($d['supplier_id']); InventoryLocation::findOrFail($d['destination_location_id']); InventorySku::findOrFail($d['inventory_sku_id']); DB::transaction(function()use($d,$a,$seq){$po=InventoryPurchaseOrder::create(['po_number'=>$seq->next(app(CurrentTenant::class)->id(),'inventory_po','PO-',7),'supplier_id'=>$d['supplier_id'],'destination_location_id'=>$d['destination_location_id'],'created_by_inventory_account_id'=>$a->id,'status'=>'ordered','ordered_at'=>now()->toDateString(),'expected_at'=>$d['expected_at']??null,'total_amount'=>(float)$d['quantity']*(float)$d['unit_cost'],'notes'=>$d['notes']??null]);$po->items()->create(['inventory_sku_id'=>$d['inventory_sku_id'],'quantity'=>$d['quantity'],'received_quantity'=>0,'unit_cost'=>$d['unit_cost']]);}); return back()->with('success','Purchase Order dibuat.'); }
    public function receive(Request $r,InventoryPurchaseOrder $purchase,InventoryLedgerService $ledger):RedirectResponse { $a=$this->manager($r); abort_unless((string)$purchase->tenant_id===app(CurrentTenant::class)->id(),404); $d=$r->validate(['purchase_item_id'=>'required|integer','quantity'=>'required|numeric|min:0.001','serials'=>'nullable|string|max:10000','notes'=>'nullable|string|max:2000']); $item=$purchase->items()->whereKey($d['purchase_item_id'])->firstOrFail(); $remaining=(float)$item->quantity-(float)$item->received_quantity; abort_if((float)$d['quantity']>$remaining+0.0001,422,'Penerimaan melebihi sisa PO.'); $sku=$item->sku()->firstOrFail(); $assets=[]; if($sku->serialized){foreach(preg_split('/\r\n|\r|\n/',trim((string)($d['serials']??''))) as $line){if(trim($line)==='')continue;[$sn,$mac,$barcode]=array_pad(array_map('trim',explode(',',$line,3)),3,'');$assets[]=['serial_number'=>$sn,'mac_address'=>$mac,'barcode'=>$barcode];}} DB::transaction(function()use($ledger,$purchase,$item,$sku,$d,$assets,$a){$ledger->receive($purchase->destination,$sku,(float)$d['quantity'],(float)$item->unit_cost,$assets,$purchase->supplier_id,$purchase->id,$a->id,null,$d['notes']??'Penerimaan '.$purchase->po_number);$item->increment('received_quantity',(float)$d['quantity']);$purchase->refresh();$total=(float)$purchase->items()->sum('quantity');$received=(float)$purchase->items()->sum('received_quantity');$purchase->update(['status'=>$received+0.0001>=$total?'received':'partial']);}); return back()->with('success','Penerimaan PO berhasil.'); }
}
