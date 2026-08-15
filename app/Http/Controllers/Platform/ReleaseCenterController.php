<?php
namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\ReleaseAcceptanceRun;
use App\Models\SecurityAuditFinding;
use App\Services\ReleaseAcceptanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReleaseCenterController extends Controller
{
    public function index()
    {
        return Inertia::render('Platform/Release',[
            'release'=>['version'=>config('jaringanku.version'),'channel'=>config('jaringanku.release_channel'),'environment'=>app()->environment()],
            'runs'=>ReleaseAcceptanceRun::query()->with('user:id,name,email')->latest('id')->limit(20)->get(),
            'findings'=>SecurityAuditFinding::query()->with(['run:id,run_uuid,version','tenant:id,name'])->latest('id')->limit(80)->get(),
        ]);
    }

    public function audit(Request $request, ReleaseAcceptanceService $service)
    {
        $strict=$request->boolean('strict_production');
        $result=$service->run(true,$request->user()?->id,$strict,'Platform release-center audit');
        $message=$result['summary']['failed']===0
            ? "Acceptance PASS: {$result['summary']['passed']} pass, {$result['summary']['warning']} warning."
            : "Acceptance FAILED: {$result['summary']['failed']} check gagal.";
        return back()->with($result['summary']['failed']===0?'success':'error',$message);
    }
}
