<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InstallationJob;
use App\Models\InventoryItem;
use App\Models\NetworkNode;
use App\Models\ServiceNetworkAssignment;
use App\Models\SupportTicket;
use App\Models\Technician;
use App\Models\WorkOrder;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class Phase11SmokeCommand extends Command
{
    protected $signature = 'jaringanku:phase11-smoke';
    protected $description = 'End-to-end data smoke test for Phase 11 ISP Operations.';

    public function handle(): int
    {
        $tenant = Tenant::query()->where('slug', env('SEED_TENANT_SLUG','demo-isp'))->first() ?: Tenant::query()->first();
        if (!$tenant) { $this->error('Tenant not found.'); return self::FAILURE; }
        app()->instance(CurrentTenant::class, new CurrentTenant($tenant));

        $customer = Customer::query()->first();
        $service = CustomerService::query()->first();
        if (!$customer || !$service) { $this->error('Customer/service demo not found. Run seeder first.'); return self::FAILURE; }

        $suffix = strtoupper(substr((string) Str::ulid(), -8));
        $created = [];
        try {
            $tech = Technician::create(['code'=>'TST-'.$suffix,'name'=>'Phase 11 Smoke Technician','status'=>'active','skills'=>['fiber','pppoe']]); $created['tech']=$tech;
            $node = NetworkNode::create(['code'=>'ODP-'.$suffix,'name'=>'Phase 11 Smoke ODP','node_type'=>'odp','status'=>'active','latitude'=>-6.2000000,'longitude'=>106.8166667,'capacity_ports'=>8,'used_ports'=>0]); $created['node']=$node;
            $ticket = SupportTicket::create(['ticket_number'=>'TKT-'.$suffix,'customer_id'=>$customer->id,'customer_service_id'=>$service->id,'assigned_technician_id'=>$tech->id,'source'=>'admin','category'=>'technical','priority'=>'normal','status'=>'open','subject'=>'Phase 11 smoke ticket','description'=>'Temporary smoke test','opened_at'=>now()]); $created['ticket']=$ticket;
            $comment = $ticket->comments()->create(['body'=>'Smoke comment','is_internal'=>false]); $created['comment']=$comment;
            $wo = WorkOrder::create(['work_order_number'=>'WO-'.$suffix,'support_ticket_id'=>$ticket->id,'customer_id'=>$customer->id,'customer_service_id'=>$service->id,'technician_id'=>$tech->id,'type'=>'repair','priority'=>'normal','status'=>'planned']); $created['wo']=$wo;
            $installation = InstallationJob::create(['installation_number'=>'INST-'.$suffix,'customer_service_id'=>$service->id,'technician_id'=>$tech->id,'work_order_id'=>$wo->id,'status'=>'planned']); $created['installation']=$installation;
            $item = InventoryItem::create(['asset_code'=>'AST-'.$suffix,'category'=>'ont','brand'=>'Smoke','model'=>'Test','status'=>'available']); $created['item']=$item;
            $movement = $item->movements()->create(['movement_type'=>'assignment','from_status'=>'available','to_status'=>'assigned_customer','customer_service_id'=>$service->id,'technician_id'=>$tech->id]); $created['movement']=$movement;
            $existingAssignment = ServiceNetworkAssignment::query()->where('customer_service_id',$service->id)->first();
            $previousAssignment = $existingAssignment ? $existingAssignment->only(['network_node_id','port_number','cable_length_m','notes']) : null;
            $assignment = ServiceNetworkAssignment::updateOrCreate(['customer_service_id'=>$service->id],['network_node_id'=>$node->id,'port_number'=>'P1','cable_length_m'=>100]); $created['assignment']=$assignment; $created['assignment_was_existing']=(bool)$existingAssignment; $created['assignment_previous']=$previousAssignment;

            if ($ticket->customer_id !== $customer->id || $wo->technician_id !== $tech->id || $installation->work_order_id !== $wo->id || $item->movements()->count() < 1 || $assignment->node->id !== $node->id) {
                throw new \RuntimeException('Relationship assertion failed.');
            }
            if (SupportTicket::query()->where('ticket_number','TKT-'.$suffix)->count() !== 1) throw new \RuntimeException('Tenant-scoped ticket lookup failed.');

            $this->line('ticket/work-order/installation/inventory/network assignment: PASS');
            $this->info('PHASE 11 ISP OPERATIONS SMOKE TEST PASSED');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        } finally {
            if (isset($created['assignment'])) { if (!empty($created['assignment_was_existing'])) $created['assignment']->update($created['assignment_previous']); else $created['assignment']->delete(); }
            if (isset($created['movement'])) $created['movement']->delete();
            if (isset($created['item'])) $created['item']->delete();
            if (isset($created['installation'])) $created['installation']->delete();
            if (isset($created['wo'])) $created['wo']->delete();
            if (isset($created['comment'])) $created['comment']->delete();
            if (isset($created['ticket'])) $created['ticket']->delete();
            if (isset($created['node'])) $created['node']->delete();
            if (isset($created['tech'])) $created['tech']->delete();
        }
    }
}
