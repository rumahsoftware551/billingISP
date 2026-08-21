<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\AutomationEvent;
use App\Models\AutomationRun;
use App\Models\BillingProfile;
use App\Models\CustomerService;
use App\Models\ServiceSuspension;
use App\Services\BillingAutomationService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function index(BillingAutomationService $automation): Response
    {
        $policy = $automation->policy();

        return Inertia::render('Operations/Index', [
            'policy' => $policy,
            'stats' => [
                'suspended_services' => CustomerService::query()->where('status', 'suspended')->count(),
                'billing_suspensions' => ServiceSuspension::query()->where('source', 'billing_automation')->where('status', 'active')->count(),
                'runs_today' => AutomationRun::query()->whereDate('started_at', today())->count(),
                'errors_today' => AutomationRun::query()->whereDate('started_at', today())->sum('error_count'),
            ],
            'suspensions' => ServiceSuspension::query()
                ->with([
                    'service:id,customer_id,service_number,pppoe_username,status',
                    'service.customer:id,customer_number,name',
                    'invoice:id,invoice_number,due_at,balance_due,status',
                    'resolvedByPayment:id,payment_number',
                ])
                ->latest('suspended_at')
                ->limit(30)
                ->get(),
            'runs' => AutomationRun::query()->latest('started_at')->limit(20)->get(),
            'events' => AutomationEvent::query()
                ->with([
                    'service:id,customer_id,service_number,pppoe_username',
                    'service.customer:id,customer_number,name',
                    'invoice:id,invoice_number',
                    'payment:id,payment_number',
                ])
                ->latest('id')
                ->limit(40)
                ->get(),
        ]);
    }

    public function updatePolicy(Request $request, BillingAutomationService $automation): RedirectResponse
    {
        $data = $request->validate([
            'grace_days' => ['required', 'integer', 'between:0,30'],
            'auto_suspend' => ['required', 'boolean'],
            'auto_reactivate' => ['required', 'boolean'],
            'disconnect_on_suspend' => ['required', 'boolean'],
        ]);

        $policy = $automation->policy();
        $policy->update($data);

        return back()->with('success', 'Kebijakan automation billing berhasil diperbarui.');
    }

    public function run(BillingAutomationService $automation): RedirectResponse
    {
        $tenant = app(CurrentTenant::class)->tenant;
        $run = $automation->evaluateTenant($tenant, 'manual', auth()->id());

        $message = sprintf(
            'Automation selesai: scanned=%d, suspended=%d, reactivated=%d, enforced=%d, errors=%d.',
            $run->scanned_count,
            $run->suspended_count,
            $run->reactivated_count,
            $run->enforced_count,
            $run->error_count,
        );

        return back()->with($run->error_count > 0 ? 'error' : 'success', $message);
    }
}
