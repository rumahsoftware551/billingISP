<?php

namespace App\Http\Controllers\FieldOps;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InstallationJob;
use App\Models\CustomerService;
use App\Models\InventoryItem;
use App\Models\NetworkNode;
use App\Models\SupportTicket;
use App\Models\Technician;
use App\Models\WorkOrder;
use Inertia\Inertia;
use Inertia\Response;

class FieldOperationsController extends Controller
{
    public function __invoke(): Response
    {
        $tickets = SupportTicket::query()->with(['customer:id,customer_number,name','service:id,service_number,pppoe_username','technician:id,code,name'])->latest()->limit(25)->get();
        $workOrders = WorkOrder::query()->with(['customer:id,customer_number,name','service:id,service_number','technician:id,code,name','ticket:id,ticket_number'])->latest()->limit(25)->get();
        $installations = InstallationJob::query()->with(['service:id,customer_id,service_number,pppoe_username,status','service.customer:id,customer_number,name','technician:id,code,name'])->latest()->limit(25)->get();
        $technicians = Technician::query()->orderBy('name')->get();
        $inventory = InventoryItem::query()->with(['service:id,service_number,pppoe_username','technician:id,code,name'])->latest()->limit(50)->get();
        $nodes = NetworkNode::query()->with('parent:id,code,name')->orderBy('node_type')->orderBy('code')->get();

        $customerPoints = Customer::query()->with(['addresses' => fn($q) => $q->whereNotNull('latitude')->whereNotNull('longitude')->orderByDesc('is_primary')])->get(['id','customer_number','name'])->flatMap(function (Customer $customer) {
            return $customer->addresses->take(1)->map(fn($a) => [
                'kind' => 'customer', 'id' => $customer->id, 'code' => $customer->customer_number, 'name' => $customer->name,
                'latitude' => (float) $a->latitude, 'longitude' => (float) $a->longitude,
            ]);
        })->values();
        $nodePoints = $nodes->filter(fn($n) => $n->latitude !== null && $n->longitude !== null)->map(fn($n) => [
            'kind'=>'node','id'=>$n->id,'code'=>$n->code,'name'=>$n->name,'node_type'=>$n->node_type,
            'latitude'=>(float)$n->latitude,'longitude'=>(float)$n->longitude,
        ])->values();

        return Inertia::render('FieldOps/Index', [
            'stats' => [
                'open_tickets' => SupportTicket::whereNotIn('status',['resolved','closed','cancelled'])->count(),
                'pending_work_orders' => WorkOrder::whereNotIn('status',['completed','cancelled'])->count(),
                'pending_installations' => InstallationJob::whereNotIn('status',['completed','cancelled'])->count(),
                'active_technicians' => Technician::where('status','active')->count(),
                'available_inventory' => InventoryItem::where('status','available')->count(),
                'network_nodes' => NetworkNode::count(),
            ],
            'tickets' => $tickets,
            'workOrders' => $workOrders,
            'installations' => $installations,
            'technicians' => $technicians,
            'inventory' => $inventory,
            'nodes' => $nodes,
            'mapPoints' => $customerPoints->concat($nodePoints)->values(),
            'customers' => Customer::query()->where('status','active')->orderBy('name')->limit(500)->get(['id','customer_number','name']),
            'services' => CustomerService::query()->with('customer:id,customer_number,name')->orderBy('service_number')->limit(1000)->get(['id','customer_id','service_number','pppoe_username','status']),
        ]);
    }
}
