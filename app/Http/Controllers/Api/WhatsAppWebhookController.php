<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppSetting;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, string $tenantSlug)
    {
        $tenant=Tenant::query()->where('slug',$tenantSlug)->firstOrFail(); app()->instance(CurrentTenant::class,new CurrentTenant($tenant));
        $setting=WhatsAppSetting::query()->firstOrFail();
        abort_unless($request->query('hub_mode')==='subscribe' || $request->query('hub.mode')==='subscribe',403);
        $token=(string)($request->query('hub_verify_token')??$request->query('hub.verify_token',''));
        abort_unless(filled($setting->verify_token)&&hash_equals((string)$setting->verify_token,$token),403);
        return response((string)($request->query('hub_challenge')??$request->query('hub.challenge','')),200)->header('Content-Type','text/plain');
    }
    public function receive(Request $request, string $tenantSlug)
    {
        $tenant=Tenant::query()->where('slug',$tenantSlug)->firstOrFail(); app()->instance(CurrentTenant::class,new CurrentTenant($tenant));
        $setting=WhatsAppSetting::query()->firstOrFail();
        if(filled($setting->app_secret)){
            $sig=(string)$request->header('X-Hub-Signature-256','');
            $expected='sha256='.hash_hmac('sha256',$request->getContent(),(string)$setting->app_secret);
            abort_unless($sig!==''&&hash_equals($expected,$sig),401);
        }
        $payload=$request->json()->all();
        foreach(data_get($payload,'entry',[]) as $entry){foreach(data_get($entry,'changes',[]) as $change){foreach(data_get($change,'value.statuses',[]) as $status){
            $id=(string)($status['id']??''); if($id==='')continue;
            WhatsAppMessageLog::query()->where('provider_message_id',$id)->update(['status'=>(string)($status['status']??'unknown'),'response'=>$status,'updated_at'=>now()]);
        }}}
        return response()->json(['ok'=>true]);
    }
}
