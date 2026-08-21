<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\CustomerService;
use App\Models\Radacct;
use App\Models\RadiusActionLog;
use App\Services\RadiusCoaService;
use App\Support\CurrentTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SessionController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = app(CurrentTenant::class)->id();
        $search = trim((string) $request->string('q'));

        $onlineQuery = $this->tenantSessionQuery($tenantId)
            ->whereNull('r.acctstoptime')
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function (Builder $q) use ($like) {
                    $q->where('r.username', 'ilike', $like)
                        ->orWhere('r.callingstationid', 'ilike', $like)
                        ->orWhereRaw('CAST(r.framedipaddress AS text) ILIKE ?', [$like])
                        ->orWhere('s.service_number', 'ilike', $like)
                        ->orWhere('c.customer_number', 'ilike', $like)
                        ->orWhere('c.name', 'ilike', $like);
                });
            });

        $online = (clone $onlineQuery)
            ->select($this->sessionSelect())
            ->orderByRaw('COALESCE(r.acctupdatetime, r.acctstarttime) DESC')
            ->paginate(25)
            ->withQueryString();

        $baseOnline = $this->tenantSessionQuery($tenantId)->whereNull('r.acctstoptime');
        $onlineCount = (clone $baseOnline)->count();
        $staleCount = (clone $baseOnline)
            ->whereRaw('COALESCE(r.acctupdatetime, r.acctstarttime) < ?', [now()->subMinutes(15)])
            ->count();

        $traffic = (clone $baseOnline)->selectRaw(
            'COALESCE(SUM(r.acctinputoctets),0) AS input_octets, COALESCE(SUM(r.acctoutputoctets),0) AS output_octets'
        )->first();

        $recent = $this->tenantSessionQuery($tenantId)
            ->whereNotNull('r.acctstoptime')
            ->select($this->sessionSelect())
            ->orderByDesc('r.acctstoptime')
            ->limit(25)
            ->get();

        $actions = RadiusActionLog::query()
            ->with([
                'service:id,service_number,pppoe_username',
                'nas:id,shortname,nasname',
                'actor:id,name',
            ])
            ->latest()
            ->limit(30)
            ->get();

        return Inertia::render('Sessions/Index', [
            'sessions' => $online,
            'recent' => $recent,
            'actions' => $actions,
            'filters' => ['q' => $search],
            'stats' => [
                'online' => $onlineCount,
                'stale' => $staleCount,
                'input_octets' => (int) ($traffic->input_octets ?? 0),
                'output_octets' => (int) ($traffic->output_octets ?? 0),
            ],
        ]);
    }

    public function disconnect(Radacct $session, RadiusCoaService $coa): RedirectResponse
    {
        $this->assertTenantSession($session);

        try {
            $result = $coa->disconnectSession($session, auth()->id());
        } catch (Throwable $e) {
            return back()->with('error', 'Disconnect gagal: '.$e->getMessage());
        }

        if (! ($result['success'] ?? false)) {
            return back()->with('error', 'NAS menolak/tidak menjawab Disconnect-Request: '.($result['response_code'] ?? 'no response'));
        }

        return back()->with('success', 'Disconnect-ACK diterima. Session akan hilang setelah NAS mengirim Accounting-Stop.');
    }

    public function coa(Radacct $session, RadiusCoaService $coa): RedirectResponse
    {
        $this->assertTenantSession($session);

        try {
            $result = $coa->applyPlanToSession($session, auth()->id());
        } catch (Throwable $e) {
            return back()->with('error', 'CoA gagal: '.$e->getMessage());
        }

        if (! ($result['success'] ?? false)) {
            return back()->with('error', 'NAS menolak/tidak menjawab CoA-Request: '.($result['response_code'] ?? 'no response'));
        }

        return back()->with('success', 'CoA-ACK diterima. Rate limit paket aktif dikirim ke session.');
    }

    private function assertTenantSession(Radacct $session): CustomerService
    {
        $service = CustomerService::withTrashed()
            ->where('pppoe_username', $session->username)
            ->first();

        abort_unless($service, 404);
        return $service;
    }

    private function tenantSessionQuery(string $tenantId): Builder
    {
        return DB::table('radacct as r')
            ->join('customer_services as s', function ($join) use ($tenantId) {
                $join->on('s.pppoe_username', '=', 'r.username')
                    ->where('s.tenant_id', '=', $tenantId);
            })
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoin('internet_plans as p', 'p.id', '=', 's.internet_plan_id')
            ->leftJoin('network_nas as n', 'n.id', '=', 's.network_nas_id')
            ->leftJoin('routers as rt', 'rt.id', '=', 's.router_id');
    }

    /** @return array<int,string> */
    private function sessionSelect(): array
    {
        return [
            'r.radacctid', 'r.acctsessionid', 'r.acctuniqueid', 'r.username',
            'r.nasipaddress', 'r.nasportid', 'r.nasporttype', 'r.acctstarttime',
            'r.acctupdatetime', 'r.acctstoptime', 'r.acctsessiontime',
            'r.acctinputoctets', 'r.acctoutputoctets', 'r.callingstationid',
            'r.calledstationid', 'r.acctterminatecause', 'r.framedipaddress', 'r.class',
            's.id as service_id', 's.service_number', 's.status as service_status',
            's.last_coa_at', 's.last_disconnect_at',
            'c.id as customer_id', 'c.customer_number', 'c.name as customer_name',
            'p.name as plan_name', 'p.code as plan_code',
            'n.id as network_nas_id', 'n.shortname as nas_shortname', 'n.coa_port',
            'rt.name as router_name',
        ];
    }
}
