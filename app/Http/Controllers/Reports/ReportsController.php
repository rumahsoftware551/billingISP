<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ReportExport;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        [$from, $to] = $this->range($request);

        return Inertia::render('Reports/Index', [
            'report' => $reports->report($from, $to),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'exports' => ReportExport::query()->with('exporter:id,name,email')->latest('id')->limit(10)->get(),
        ]);
    }

    public function export(Request $request, string $type, ReportService $reports): StreamedResponse
    {
        abort_unless(in_array($type, ['customers','services','invoices','outstanding','payments','sessions'], true), 404);
        [$from, $to] = $this->range($request);
        $dataset = $reports->exportDataset($type, $from, $to);

        ReportExport::create([
            'report_type' => $type,
            'format' => 'csv',
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'row_count' => count($dataset['rows']),
            'exported_by' => $request->user()?->id,
            'exported_at' => now(),
        ]);

        $filename = sprintf('jaringanku-%s-%s-%s.csv', $type, $from->format('Ymd'), $to->format('Ymd'));
        $rows = array_map(fn (array $row) => array_map([$this, 'csvCell'], $row), $dataset['rows']);

        return response()->streamDownload(function () use ($dataset, $rows) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $dataset['headers'], ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvCell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }
        return $value;
    }

    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable','date_format:Y-m-d'],
            'to' => ['nullable','date_format:Y-m-d'],
        ]);

        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->startOfDay() : CarbonImmutable::today();
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'])->startOfDay() : $to->subMonths(5)->startOfMonth();

        abort_if($from->greaterThan($to), 422, 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
        abort_if($from->diffInDays($to) > 1096, 422, 'Rentang laporan maksimal 3 tahun.');

        return [$from, $to];
    }
}
