<?php

namespace App\Console\Commands;

use App\Models\InstallationJob;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\NetworkNode;
use App\Models\ServiceNetworkAssignment;
use App\Models\SupportTicket;
use App\Models\Technician;
use App\Models\TicketComment;
use App\Models\WorkOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class Phase11PreflightCommand extends Command
{
    protected $signature = 'jaringanku:phase11-preflight';
    protected $description = 'Validate Phase 11 ISP Operations schema, model mappings, and routes.';

    public function handle(): int
    {
        $tables = ['technicians','network_nodes','support_tickets','ticket_comments','work_orders','installation_jobs','inventory_items','inventory_movements','service_network_assignments'];
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("Missing table: {$table}");
                return self::FAILURE;
            }
        }

        $mapping = [
            Technician::class=>'technicians', NetworkNode::class=>'network_nodes', SupportTicket::class=>'support_tickets',
            TicketComment::class=>'ticket_comments', WorkOrder::class=>'work_orders', InstallationJob::class=>'installation_jobs',
            InventoryItem::class=>'inventory_items', InventoryMovement::class=>'inventory_movements', ServiceNetworkAssignment::class=>'service_network_assignments',
        ];
        foreach ($mapping as $class=>$table) {
            if ((new $class)->getTable() !== $table) {
                $this->error("Bad model table mapping: {$class}");
                return self::FAILURE;
            }
        }

        foreach (['field-operations.index','field-operations.tickets.store','field-operations.work-orders.store','field-operations.installations.store','field-operations.inventory.store','field-operations.nodes.store','portal.tickets.index','portal.tickets.store'] as $route) {
            if (!Route::has($route)) {
                $this->error("Missing route: {$route}");
                return self::FAILURE;
            }
        }

        $this->info('PHASE 11 PREFLIGHT PASSED');
        return self::SUCCESS;
    }
}
