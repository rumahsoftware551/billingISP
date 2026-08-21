<?php
namespace App\Http\Controllers\FieldOps;
use App\Http\Controllers\Controller;
use App\Models\NetworkNode;
use App\Models\ServiceNetworkAssignment;
use App\Models\CustomerService;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class NetworkNodeController extends Controller {
 public function store(Request $r,TenantSequenceService $seq):RedirectResponse { $d=$r->validate(['name'=>'required|string|max:160','node_type'=>['required',Rule::in(['pop','olt','odc','odp','splitter','other'])],'parent_node_id'=>'nullable|integer','address'=>'nullable|string|max:2000','latitude'=>'nullable|numeric|between:-90,90','longitude'=>'nullable|numeric|between:-180,180','capacity_ports'=>'nullable|integer|min:1|max:65535','notes'=>'nullable|string|max:2000']); if(!empty($d['parent_node_id']))NetworkNode::findOrFail($d['parent_node_id']); NetworkNode::create([...$d,'code'=>$seq->next(app(CurrentTenant::class)->id(),'network_node',strtoupper($d['node_type']).'-',4),'status'=>'active','used_ports'=>0]); return back()->with('success','Node jaringan ditambahkan.'); }
 public function assignService(Request $r):RedirectResponse { $d=$r->validate(['customer_service_id'=>'required|integer','network_node_id'=>'required|integer','port_number'=>'nullable|string|max:40','cable_length_m'=>'nullable|integer|min:0|max:100000','notes'=>'nullable|string|max:2000']); $service=CustomerService::findOrFail($d['customer_service_id']); $node=NetworkNode::findOrFail($d['network_node_id']); $existing=ServiceNetworkAssignment::where('customer_service_id',$service->id)->first(); $oldNodeId=$existing?->network_node_id; $currentUsage=ServiceNetworkAssignment::where('network_node_id',$node->id)->when($existing,fn($q)=>$q->where('id','!=',$existing->id))->count(); if($node->capacity_ports!==null && $currentUsage >= (int)$node->capacity_ports) return back()->with('error','Kapasitas port node sudah penuh.'); ServiceNetworkAssignment::updateOrCreate(['customer_service_id'=>$service->id],$d); foreach(array_unique(array_filter([$oldNodeId,$node->id])) as $nodeId){ NetworkNode::whereKey($nodeId)->update(['used_ports'=>ServiceNetworkAssignment::where('network_node_id',$nodeId)->count()]); } return back()->with('success','Service berhasil dipetakan ke node jaringan.'); }
}
