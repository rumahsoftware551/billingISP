<?php
namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformEvent;
use App\Models\PlatformPlan;
use Illuminate\Http\Request;

class PlatformPlanController extends Controller
{
    public function update(Request $request, PlatformPlan $plan)
    {
        $data = $request->validate([
            'name'=>['required','string','max:100'],
            'monthly_price'=>['required','integer','min:0'],
            'max_customers'=>['nullable','integer','min:1'],
            'max_services'=>['nullable','integer','min:1'],
            'max_routers'=>['nullable','integer','min:1'],
            'max_users'=>['nullable','integer','min:1'],
            'active'=>['required','boolean'],
        ]);
        $plan->update($data);
        PlatformEvent::create(['user_id'=>$request->user()->id,'event'=>'plan.updated','payload'=>['plan'=>$plan->code]]);
        return back()->with('success','Paket SaaS berhasil diperbarui.');
    }
}
