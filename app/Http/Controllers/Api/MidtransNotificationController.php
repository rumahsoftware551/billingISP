<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\PaymentGatewayNotificationService;
use Illuminate\Http\Request;
class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, PaymentGatewayNotificationService $service)
    {
        $payload=$request->json()->all();
        $result=$service->handleMidtrans($payload,$request->getContent());
        $status=(int)($result['http']??200);
        return response()->json(['ok'=>$result['ok']??false,'status'=>$result['status']??null,'duplicate'=>$result['duplicate']??false,'message'=>$result['message']??null],$status);
    }
}
