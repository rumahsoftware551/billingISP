<?php
namespace App\Http\Controllers\Inventory;
use App\Http\Controllers\Controller;
use App\Models\InventorySku;
use App\Models\InventorySupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
class InventoryCatalogController extends Controller {
    private function manager(Request $r):void { abort_unless($r->attributes->get('inventory_account')?->isManager(),403); }
    public function sku(Request $r):RedirectResponse { $this->manager($r); $d=$r->validate(['sku'=>'required|string|max:80','name'=>'required|string|max:180','category'=>'required|string|max:80','brand'=>'nullable|string|max:100','model'=>'nullable|string|max:120','uom'=>'required|string|max:30','minimum_stock'=>'nullable|numeric|min:0','serialized'=>'nullable|boolean','track_mac'=>'nullable|boolean']); InventorySku::create([...$d,'minimum_stock'=>$d['minimum_stock']??0,'serialized'=>(bool)($d['serialized']??false),'track_mac'=>(bool)($d['track_mac']??false),'active'=>true]); return back()->with('success','SKU inventory dibuat.'); }
    public function supplier(Request $r):RedirectResponse { $this->manager($r); $d=$r->validate(['code'=>'required|string|max:60','name'=>'required|string|max:180','contact_name'=>'nullable|string|max:160','phone'=>'nullable|string|max:50','email'=>'nullable|email|max:190','address'=>'nullable|string|max:2000','tax_number'=>'nullable|string|max:80','notes'=>'nullable|string|max:2000']); InventorySupplier::create([...$d,'active'=>true]); return back()->with('success','Supplier ditambahkan.'); }
}
