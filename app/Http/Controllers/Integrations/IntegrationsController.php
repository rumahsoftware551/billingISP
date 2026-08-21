<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentGatewayTransaction;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppSetting;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class IntegrationsController extends Controller
{
    public function index()
    {
        $payment = PaymentGatewaySetting::query()->first();
        $whatsapp = WhatsAppSetting::query()->first();
        return Inertia::render('Integrations/Index', [
            'payment' => $payment ? [
                'provider'=>$payment->provider,'environment'=>$payment->environment,'enabled'=>$payment->enabled,
                'merchant_id'=>$payment->merchant_id,'has_client_key'=>filled($payment->client_key),'has_server_key'=>filled($payment->server_key),
                'enabled_payments'=>$payment->enabled_payments ?: [],'expiry_minutes'=>$payment->expiry_minutes,
                'last_tested_at'=>$payment->last_tested_at,'last_test_status'=>$payment->last_test_status,'last_error'=>$payment->last_error,
            ] : null,
            'whatsapp' => $whatsapp ? [
                'provider'=>$whatsapp->provider,'mode'=>$whatsapp->mode,'enabled'=>$whatsapp->enabled,'graph_version'=>$whatsapp->graph_version,
                'phone_number_id'=>$whatsapp->phone_number_id,'business_account_id'=>$whatsapp->business_account_id,
                'has_access_token'=>filled($whatsapp->access_token),'has_app_secret'=>filled($whatsapp->app_secret),'has_verify_token'=>filled($whatsapp->verify_token),
                'default_country_code'=>$whatsapp->default_country_code,'template_language'=>$whatsapp->template_language,'template_map'=>$whatsapp->template_map ?: [],
                'last_tested_at'=>$whatsapp->last_tested_at,'last_test_status'=>$whatsapp->last_test_status,'last_error'=>$whatsapp->last_error,
            ] : null,
            'transactions' => PaymentGatewayTransaction::query()->with('invoice:id,invoice_number')->latest('id')->limit(20)->get(),
            'whatsappLogs' => WhatsAppMessageLog::query()->latest('id')->limit(20)->get(),
        ]);
    }

    public function updatePayment(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'provider'=>['required',Rule::in(['mock','midtrans'])], 'environment'=>['required',Rule::in(['sandbox','production'])], 'enabled'=>['required','boolean'],
            'merchant_id'=>['nullable','string','max:100'],'client_key'=>['nullable','string','max:500'],'server_key'=>['nullable','string','max:500'],
            'enabled_payments'=>['nullable','array','max:20'],'enabled_payments.*'=>['string','max:40'],'expiry_minutes'=>['required','integer','min:5','max:10080'],
        ]);
        if ($data['provider']==='mock' && ! app()->environment('local')) {
            return back()->with('error','Mock gateway hanya boleh digunakan pada local environment.');
        }
        $setting = PaymentGatewaySetting::query()->firstOrNew();
        $old = $setting->exists ? ['provider'=>$setting->provider,'environment'=>$setting->environment,'enabled'=>$setting->enabled] : null;
        foreach (['provider','environment','enabled','merchant_id','enabled_payments','expiry_minutes'] as $key) $setting->{$key}=$data[$key]??null;
        foreach (['client_key','server_key'] as $key) if (filled($data[$key]??null)) $setting->{$key}=$data[$key];
        $setting->save();
        $audit->record('integration.payment.updated', PaymentGatewaySetting::class, $setting->id, $old, ['provider'=>$setting->provider,'environment'=>$setting->environment,'enabled'=>$setting->enabled]);
        return back()->with('success','Konfigurasi payment gateway disimpan. Secret kosong tidak menimpa secret lama.');
    }

    public function updateWhatsApp(Request $request, AuditService $audit)
    {
        $data=$request->validate([
            'mode'=>['required',Rule::in(['log','cloud'])],'enabled'=>['required','boolean'],'graph_version'=>['required','regex:/^v\\d+\\.\\d+$/'],
            'phone_number_id'=>['nullable','string','max:100'],'business_account_id'=>['nullable','string','max:100'],
            'access_token'=>['nullable','string','max:1000'],'app_secret'=>['nullable','string','max:1000'],'verify_token'=>['nullable','string','max:500'],
            'default_country_code'=>['required','regex:/^\\d{1,5}$/'],'template_language'=>['required','string','max:16'],'template_map'=>['nullable','array'],'template_map.*'=>['nullable','string','max:160'],
        ]);
        if ($data['mode']==='log' && ! app()->environment('local')) return back()->with('error','WhatsApp LOG mode hanya untuk local environment.');
        $setting=WhatsAppSetting::query()->firstOrNew();
        $old=$setting->exists?['mode'=>$setting->mode,'enabled'=>$setting->enabled,'graph_version'=>$setting->graph_version]:null;
        $cloudEnabled = $data['mode'] === 'cloud' && (bool) $data['enabled'];
        $accessToken = filled($data['access_token'] ?? null) ? $data['access_token'] : $setting->access_token;
        $appSecret = filled($data['app_secret'] ?? null) ? $data['app_secret'] : $setting->app_secret;
        $verifyToken = filled($data['verify_token'] ?? null) ? $data['verify_token'] : $setting->verify_token;
        if ($cloudEnabled && (! filled($accessToken) || ! filled($appSecret) || ! filled($verifyToken))) {
            return back()->with('error', 'WhatsApp Cloud tidak dapat diaktifkan sebelum access token, app secret, dan verify token terisi.');
        }
        foreach(['mode','enabled','graph_version','phone_number_id','business_account_id','default_country_code','template_language','template_map'] as $key)$setting->{$key}=$data[$key]??null;
        foreach(['access_token','app_secret','verify_token'] as $key)if(filled($data[$key]??null))$setting->{$key}=$data[$key];
        $setting->provider='meta_cloud'; $setting->save();
        $audit->record('integration.whatsapp.updated',WhatsAppSetting::class,$setting->id,$old,['mode'=>$setting->mode,'enabled'=>$setting->enabled,'graph_version'=>$setting->graph_version]);
        return back()->with('success','Konfigurasi WhatsApp disimpan. Secret kosong tidak menimpa secret lama.');
    }

    public function testWhatsApp(Request $request, NotificationService $notifications, AuditService $audit)
    {
        $data=$request->validate(['recipient'=>['required','string','max:50']]);
        $notification=$notifications->queue('whatsapp',$data['recipient'],'Jaringanku WhatsApp Test','Pesan uji integrasi WhatsApp Jaringanku Phase 09.',['source'=>'phase09-test']);
        $audit->record('integration.whatsapp.test',get_class($notification),$notification->id,null,['recipient'=>$data['recipient']]);
        return back()->with('success','Pesan WhatsApp test dimasukkan ke queue. Cek status pada Integration Logs.');
    }
}
