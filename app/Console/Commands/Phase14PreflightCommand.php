<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
class Phase14PreflightCommand extends Command {
 protected $signature='jaringanku:phase14-preflight'; protected $description='Validate Phase 14 Inventory Portal schema, model mappings, and routes.';
 public function handle():int {
  foreach(['inventory_locations','inventory_portal_accounts','inventory_login_events','inventory_skus','inventory_suppliers','inventory_purchase_orders','inventory_purchase_order_items','inventory_balances','inventory_transactions','inventory_transaction_lines','inventory_stock_opnames','inventory_stock_opname_lines'] as $table){if(!Schema::hasTable($table)){$this->error("Table {$table} tidak tersedia.");return self::FAILURE;}}
  foreach([['inventory_items','inventory_sku_id'],['inventory_items','current_location_id'],['inventory_items','barcode'],['inventory_items','condition'],['inventory_movements','inventory_transaction_id'],['inventory_movements','from_location_id'],['inventory_movements','to_location_id']] as [$t,$c]){if(!Schema::hasColumn($t,$c)){$this->error("Column {$t}.{$c} tidak tersedia.");return self::FAILURE;}}
  $maps=[[new \App\Models\InventoryPortalAccount,'inventory_portal_accounts'],[new \App\Models\InventoryLocation,'inventory_locations'],[new \App\Models\InventorySku,'inventory_skus'],[new \App\Models\InventoryBalance,'inventory_balances'],[new \App\Models\InventoryTransaction,'inventory_transactions']];foreach($maps as [$m,$e]){if($m->getTable()!==$e){$this->error("Model mapping {$e} tidak sesuai.");return self::FAILURE;}}
  foreach(['inventory.login','inventory.dashboard','inventory.receive','inventory.transfer','inventory.install','inventory.retrieve','inventory.opname','inventory.purchases.store','inventory-admin.index'] as $name){if(!Route::has($name)){$this->error("Route {$name} tidak terdaftar.");return self::FAILURE;}}
  $this->info('PHASE 14 INVENTORY PORTAL PREFLIGHT PASSED'); return self::SUCCESS;
 }
}
