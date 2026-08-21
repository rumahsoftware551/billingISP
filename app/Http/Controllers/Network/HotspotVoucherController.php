<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\HotspotProfile;
use App\Models\HotspotVoucher;
use App\Models\HotspotVoucherBatch;
use App\Services\HotspotVoucherService;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HotspotVoucherController extends Controller
{
    public function index(Request $request): Response
    {
        $status = trim((string) $request->string('status'));
        $search = trim((string) $request->string('q'));

        return Inertia::render('Hotspot/Index', [
            'profiles' => HotspotProfile::query()
                ->withCount(['vouchers', 'vouchers as available_count' => fn ($query) => $query->where('status', 'available')])
                ->orderBy('name')
                ->get(),
            'batches' => HotspotVoucherBatch::query()
                ->with('profile:id,name,code')
                ->withCount([
                    'vouchers',
                    'vouchers as available_count' => fn ($query) => $query->where('status', 'available'),
                    'vouchers as sold_count' => fn ($query) => $query->whereIn('status', ['sold', 'active', 'expired', 'disabled']),
                ])
                ->latest()
                ->limit(30)
                ->get(),
            'vouchers' => HotspotVoucher::query()
                ->with(['profile:id,name,code,price', 'batch:id,batch_code'])
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($search !== '', fn ($query) => $query->where('username', 'ilike', '%'.$search.'%'))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'stats' => HotspotVoucher::query()->selectRaw(
                "COUNT(*) AS total, COUNT(*) FILTER (WHERE status='available') AS available, COUNT(*) FILTER (WHERE status='sold') AS sold, COUNT(*) FILTER (WHERE status='active') AS active, COUNT(*) FILTER (WHERE status='expired') AS expired"
            )->first(),
            'revenue' => (int) HotspotVoucher::query()->whereNotNull('sold_at')->sum('sold_price'),
            'filters' => ['status' => $status, 'q' => $search],
        ]);
    }

    public function storeProfile(Request $request): RedirectResponse
    {
        $tenantId = app(CurrentTenant::class)->id();
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('hotspot_profiles', 'code')->where('tenant_id', $tenantId)],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'validity_minutes' => ['required', 'integer', 'between:30,525600'],
            'session_timeout_minutes' => ['required', 'integer', 'between:5,525600', 'lte:validity_minutes'],
            'idle_timeout_minutes' => ['required', 'integer', 'between:1,1440'],
            'simultaneous_use' => ['required', 'integer', 'between:1,10'],
            'activation_deadline_days' => ['required', 'integer', 'between:1,365'],
            'rate_limit' => ['required', 'string', 'max:120', 'regex:/^\d+[kKmM]?\/\d+[kKmM]?$/'],
        ]);

        HotspotProfile::query()->create([
            ...$data,
            'tenant_id' => $tenantId,
            'code' => strtoupper($data['code']),
            'active' => true,
        ]);

        return back()->with('success', 'Profil voucher hotspot berhasil dibuat.');
    }

    public function storeBatch(Request $request, HotspotVoucherService $service): RedirectResponse
    {
        $tenantId = app(CurrentTenant::class)->id();
        $request->merge(['prefix' => strtoupper(trim((string) $request->input('prefix')))]);
        $data = $request->validate([
            'hotspot_profile_id' => ['required', Rule::exists('hotspot_profiles', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('active', true))],
            'quantity' => ['required', 'integer', 'between:1,1000'],
            'prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9]+$/'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $profile = HotspotProfile::query()->findOrFail($data['hotspot_profile_id']);
        $batch = $service->generateBatch(
            $profile,
            (int) $data['quantity'],
            strtoupper($data['prefix']),
            $data['idempotency_key'],
            $request->user()?->id,
        );

        return back()->with('success', $batch->getAttribute('idempotent_replay')
            ? 'Permintaan batch ini sudah diproses sebelumnya.'
            : 'Batch '.$batch->batch_code.' berisi '.$batch->quantity.' voucher berhasil dibuat.');
    }

    public function sell(Request $request, HotspotVoucher $voucher, HotspotVoucherService $service): RedirectResponse
    {
        $this->ensureTenantOwnership($voucher);
        $data = $request->validate([
            'method' => ['required', Rule::in(['cash', 'transfer', 'qris'])],
            'reference' => ['nullable', 'string', 'max:160'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $sold = $service->sell(
            $voucher,
            $data['method'],
            $data['reference'] ?? null,
            $data['idempotency_key'],
            $request->user()?->id,
        );

        return back()->with('success', $sold->getAttribute('idempotent_replay')
            ? 'Penjualan voucher ini sudah tercatat sebelumnya.'
            : 'Voucher '.$sold->username.' terjual dan akses RADIUS diaktifkan.');
    }

    public function disable(HotspotVoucher $voucher, HotspotVoucherService $service): RedirectResponse
    {
        $this->ensureTenantOwnership($voucher);
        $service->disable($voucher);
        return back()->with('success', 'Voucher dinonaktifkan dan RADIUS akan menolak login berikutnya.');
    }

    public function enable(HotspotVoucher $voucher, HotspotVoucherService $service): RedirectResponse
    {
        $this->ensureTenantOwnership($voucher);
        $service->enable($voucher);
        return back()->with('success', 'Voucher diaktifkan kembali.');
    }

    public function export(HotspotVoucherBatch $batch): StreamedResponse
    {
        $this->ensureTenantOwnership($batch);
        $batch->load('profile:id,name,code,price');
        $filename = preg_replace('/[^A-Za-z0-9_.-]/', '-', $batch->batch_code).'.csv';

        return response()->streamDownload(function () use ($batch): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['batch', 'profile', 'username', 'password', 'price', 'status']);
            $batch->vouchers()->orderBy('id')->chunkById(200, function ($vouchers) use ($output, $batch): void {
                foreach ($vouchers as $voucher) {
                    fputcsv($output, [
                        $this->safeCsv($batch->batch_code),
                        $this->safeCsv($batch->profile->name),
                        $voucher->username,
                        $voucher->password,
                        $batch->profile->price,
                        $voucher->status,
                    ]);
                }
            });
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function safeCsv(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
