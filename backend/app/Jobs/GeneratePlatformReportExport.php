<?php

namespace App\Jobs;

use App\Models\{PlatformInvoice, PlatformPayment, ReportExport};
use App\Services\PlatformAnalyticsService;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

final class GeneratePlatformReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $exportId) {}

    public function handle(PlatformAnalyticsService $analytics, TenantContext $context): void
    {
        $context->setPlatformAdmin(true);
        $export = ReportExport::where('platform_scope', true)->findOrFail($this->exportId);
        try {
            $query = $analytics->exportQuery($export->report_key, $export->filters ?? []);
            if (! $query || ! in_array($export->format, ['csv'], true)) throw new \RuntimeException('This platform export format or report is not available.');
            $path = 'private/platform-reports/' . $export->id . '.csv';
            $stream = fopen('php://temp', 'w+');
            $headers = $export->report_key === 'payments' ? ['Payment reference', 'Business', 'Invoice', 'Amount', 'Currency', 'Verified at'] : ['Invoice number', 'Business', 'Status', 'Total', 'Paid', 'Currency', 'Issued on', 'Due on'];
            fputcsv($stream, $headers);
            foreach ($query->cursor() as $row) {
                $values = $row instanceof PlatformPayment
                    ? [$row->provider_payment_id, $row->tenant?->name, $row->invoice?->number, $row->amount, $row->currency_code, $row->verified_at?->toISOString()]
                    : [$row->number, $row->tenant?->name, $row->status, $row->total, $row->paid_total, $row->currency_code, $row->issued_on?->toDateString(), $row->due_on?->toDateString()];
                fputcsv($stream, array_map([$this, 'safeCell'], $values));
            }
            rewind($stream); Storage::disk('local')->put($path, stream_get_contents($stream)); fclose($stream);
            $export->update(['state' => 'completed', 'private_path' => $path, 'completed_at' => now(), 'error' => null]);
        } catch (\Throwable $exception) {
            $export->update(['state' => 'failed', 'error' => 'The report export failed.']);
            throw $exception;
        } finally {
            $context->setPlatformAdmin(false);
        }
    }

    private function safeCell(mixed $value): string
    {
        $cell = str_replace(["\r", "\n"], ' ', (string) $value);
        return preg_match('/^[=+\-@]/', $cell) ? "'{$cell}" : $cell;
    }
}
