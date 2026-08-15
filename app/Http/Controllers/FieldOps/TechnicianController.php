<?php
namespace App\Http\Controllers\FieldOps;
use App\Http\Controllers\Controller;
use App\Models\Technician;
use App\Services\TenantSequenceService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class TechnicianController extends Controller {
 public function store(Request $r,TenantSequenceService $seq):RedirectResponse { $d=$r->validate(['name'=>'required|string|max:160','phone'=>'nullable|string|max:40','email'=>'nullable|email|max:190','skills'=>'nullable|string|max:500','notes'=>'nullable|string|max:2000']); Technician::create(['code'=>$seq->next(app(CurrentTenant::class)->id(),'technician','TECH-',4),'name'=>$d['name'],'phone'=>$d['phone']??null,'email'=>$d['email']??null,'status'=>'active','skills'=>array_values(array_filter(array_map('trim',explode(',',$d['skills']??'')))),'notes'=>$d['notes']??null]); return back()->with('success','Teknisi berhasil ditambahkan.'); }
 public function update(Request $r,Technician $technician):RedirectResponse { abort_unless((string)$technician->tenant_id===app(CurrentTenant::class)->id(),404); $d=$r->validate(['status'=>['required',Rule::in(['active','inactive','leave'])]]); $technician->update($d); return back()->with('success','Status teknisi diperbarui.'); }
}
