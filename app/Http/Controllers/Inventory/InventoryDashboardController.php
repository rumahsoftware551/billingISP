<?php
namespace App\Http\Controllers\Inventory;
use App\Http\Controllers\Controller;
use App\Models\CustomerService;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventorySku;
use App\Models\InventorySupplier;
use App\Models\InventoryTransaction;
use App\Models\Technician;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class InventoryDashboardController extends Controller {
    public function __invoke(Request $r):Response {
        $a=$r->attributes->get('inventory_account');
        $balances=InventoryBalance::query()->with(['location:id,code,name,location_type','sku:id,sku,name,category,uom,minimum_stock,serialized'])->orderByDesc('quantity_on_hand')->limit(150)->get();
        $low=InventoryBalance::query()->join('inventory_skus','inventory_skus.id','=','inventory_balances.inventory_sku_id')->whereColumn('inventory_balances.quantity_on_hand','<=','inventory_skus.minimum_stock')->where('inventory_skus.minimum_stock','>',0)->count();
        return Inertia::render('Inventory/Dashboard',[
            'stats'=>['sku'=>InventorySku::query()->where('active',true)->count(),'assets'=>InventoryItem::query()->count(),'available_assets'=>InventoryItem::query()->where('status','available')->count(),'installed_assets'=>InventoryItem::query()->where('status','assigned_customer')->count(),'low_stock'=>$low,'locations'=>InventoryLocation::query()->where('active',true)->count()],
            'locations'=>InventoryLocation::query()->with('technician:id,code,name')->where('active',true)->orderBy('name')->get(),
            'skus'=>InventorySku::query()->where('active',true)->orderBy('name')->get(),
            'balances'=>$balances,
            'assets'=>InventoryItem::query()->with(['sku:id,sku,name,serialized','location:id,code,name','service:id,service_number,customer_id'])->latest()->limit(100)->get(),
            'transactions'=>InventoryTransaction::query()->with(['fromLocation:id,code,name','toLocation:id,code,name','lines.sku:id,sku,name'])->latest('occurred_at')->limit(60)->get(),
            'suppliers'=>InventorySupplier::query()->where('active',true)->orderBy('name')->get(),
            'purchases'=>InventoryPurchaseOrder::query()->with(['supplier:id,code,name','destination:id,code,name','items.sku:id,sku,name'])->latest()->limit(40)->get(),
            'technicians'=>Technician::query()->where('status','active')->orderBy('name')->get(['id','code','name']),
            'services'=>CustomerService::query()->with('customer:id,customer_number,name')->whereIn('status',['active','pending_installation'])->orderBy('service_number')->limit(250)->get(['id','customer_id','service_number','status']),
            'account'=>['id'=>$a->id,'name'=>$a->name,'role'=>$a->role,'location_id'=>$a->inventory_location_id,'technician_id'=>$a->technician_id],
        ]);
    }
}
