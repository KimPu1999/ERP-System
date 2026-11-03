<?php

namespace App\Jobs;

use App\Models\{MonitoringAlert, PlatformAuditLog, PlatformInvoice, PlatformPayment, ReportExport, SecurityEvent, User};
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
            $security = $this->securityQuery($export->report_key, $export->filters ?? []);
            $query = $security['query'] ?? $analytics->exportQuery($export->report_key, $export->filters ?? []);
            if (! $query || ! in_array($export->format, ['csv', 'xlsx', 'pdf'], true)) {
                throw new \RuntimeException('This platform export format or report is not available.');
            }

            $headers = $security['headers'] ?? ($export->report_key === 'payments'
                ? ['Payment reference', 'Business', 'Invoice', 'Amount', 'Currency', 'Verified at']
                : ['Invoice number', 'Business', 'Status', 'Total', 'Paid', 'Currency', 'Issued on', 'Due on']);
            $rows = [];
            $maxRows = max(1, (int) config('security.max_export_rows', 10000));
            foreach ($query->limit($maxRows + 1)->cursor() as $row) {
                if (count($rows) >= $maxRows) {
                    throw new \RuntimeException('The report exceeds the configured export row limit.');
                }
                $values = isset($security['map']) ? ($security['map'])($row) : ($row instanceof PlatformPayment
                    ? [$row->provider_payment_id, $row->tenant?->name, $row->invoice?->number, $row->amount, $row->currency_code, $row->verified_at?->toISOString()]
                    : [$row->number, $row->tenant?->name, $row->status, $row->total, $row->paid_total, $row->currency_code, $row->issued_on?->toDateString(), $row->due_on?->toDateString()]);
                $rows[] = array_map([$this, 'safeCell'], $values);
            }

            $extension = $export->format;
            $path = 'private/platform-reports/' . $export->id . '.' . $extension;
            $content = match ($export->format) {
                'csv' => $this->buildCsv($headers, $rows),
                'xlsx' => $this->buildXlsx($headers, $rows),
                'pdf' => $this->buildPdf($headers, $rows),
            };
            Storage::disk('local')->put($path, $content);
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

    private function buildCsv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) fputcsv($stream, $row);
        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);
        return $content;
    }

    private function buildXlsx(array $headers, array $rows): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'erp-export-');
        if ($temporary === false) throw new \RuntimeException('Unable to allocate export storage.');
        $zip = new \ZipArchive();
        if ($zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Unable to create spreadsheet export.');
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $allRows = array_merge([$headers], $rows);
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($allRows as $rowIndex => $row) {
            $sheet .= '<row r="' . ($rowIndex + 1) . '">';
            foreach ($row as $columnIndex => $value) {
                $cell = $this->columnName($columnIndex + 1) . ($rowIndex + 1);
                $sheet .= '<c r="' . $cell . '" t="inlineStr"><is><t xml:space="preserve">' . $this->xml((string) $value) . '</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        $content = file_get_contents($temporary);
        @unlink($temporary);
        if ($content === false) throw new \RuntimeException('Unable to read spreadsheet export.');
        return $content;
    }

    private function buildPdf(array $headers, array $rows): string
    {
        $lines = [implode(' | ', array_map(fn (string $value): string => $this->pdfText($value, 32), $headers))];
        foreach ($rows as $row) $lines[] = implode(' | ', array_map(fn (string $value): string => $this->pdfText($value, 32), $row));
        $pages = array_chunk($lines, 54);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pageRefs = [];
        foreach ($pages ?: [[]] as $index => $pageLines) {
            $pageId = 4 + ($index * 2); $contentId = $pageId + 1; $pageRefs[] = $pageId . ' 0 R';
            $stream = "BT\n/F1 7 Tf\n36 780 Td\n";
            foreach ($pageLines as $line) $stream .= '(' . $this->pdfEscape($line) . ") Tj\n0 -14 Td\n";
            $stream .= "ET\n";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($pageRefs) . ' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offsets = [0 => 0];
        foreach ($objects as $id => $object) { $offsets[$id] = strlen($pdf); $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n"; }
        $xref = strlen($pdf); $pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= max(array_keys($objects)); $id++) $pdf .= sprintf('%010d 00000 n \n', $offsets[$id] ?? 0);
        $pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }

    private function columnName(int $number): string { $name = ''; while ($number > 0) { $remainder = ($number - 1) % 26; $name = chr(65 + $remainder) . $name; $number = intdiv($number - 1, 26); } return $name; }
    private function xml(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    private function pdfText(string $value, int $limit): string { $value = preg_replace('/\s+/', ' ', $value) ?? ''; return mb_strimwidth($value, 0, $limit, '…'); }
    private function pdfEscape(string $value): string { return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value); }

    private function securityQuery(string $key, array $filters): array
    {
        $from = $filters['from'] ?? null; $to = $filters['to'] ?? null;
        $range = static function ($query) use ($from, $to, $filters): void { if ($from) $query->where('created_at', '>=', $from . ' 00:00:00'); if ($to) $query->where('created_at', '<=', $to . ' 23:59:59'); if (! empty($filters['result'])) $query->where('result', $filters['result']); if (! empty($filters['category'])) $query->where('category', $filters['category']); if (! empty($filters['tenant_id'])) $query->where('tenant_id', $filters['tenant_id']); };
        if (in_array($key, ['login_activity', 'failed_logins', 'session_revocations'], true)) {
            $query = SecurityEvent::query()->with('user:id,name,email')->latest('created_at');
            if ($key === 'failed_logins') $query->where('result', 'failure');
            if ($key === 'session_revocations') $query->whereIn('event', ['auth.session_revoked', 'auth.sessions_revoked']);
            if (! empty($filters['actor_user_id'])) $query->where('user_id', $filters['actor_user_id']);
            $range($query);
            return ['query' => $query, 'headers' => ['Event', 'Account', 'Result', 'IP', 'User agent', 'Created at'], 'map' => fn (SecurityEvent $event): array => [$event->event, $event->user?->email, $event->result, $event->ip_address, $event->user_agent, $event->created_at?->toISOString()]];
        }
        if (in_array($key, ['administrator_activity', 'permission_changes', 'audit_events'], true)) {
            $query = PlatformAuditLog::query()->with('actor:id,name,email')->latest('created_at');
            if ($key === 'permission_changes') $query->where('category', 'authorization');
            if (! empty($filters['actor_user_id'])) $query->where('actor_user_id', $filters['actor_user_id']);
            $range($query);
            return ['query' => $query, 'headers' => ['Action', 'Category', 'Result', 'Actor', 'Resource type', 'Resource ID', 'Created at'], 'map' => fn (PlatformAuditLog $log): array => [$log->action, $log->category, $log->result, $log->actor?->email, $log->resource_type, $log->auditable_id, $log->created_at?->toISOString()]];
        }
        if ($key === 'mfa_enrollment') {
            $query = User::query()->where('is_platform_super_admin', true)->latest('created_at');
            return ['query' => $query, 'headers' => ['Administrator', 'Email', 'MFA enrolled', 'MFA enabled at', 'Last login'], 'map' => fn (User $user): array => [$user->name, $user->email, $user->mfa_enabled_at ? 'yes' : 'no', $user->mfa_enabled_at?->toISOString(), $user->last_login_at?->toISOString()]];
        }
        if ($key === 'security_alerts') {
            $query = MonitoringAlert::query()->latest('last_observed_at');
            if (! empty($filters['severity'])) $query->where('severity', $filters['severity']);
            if ($from) $query->where('created_at', '>=', $from . ' 00:00:00'); if ($to) $query->where('created_at', '<=', $to . ' 23:59:59');
            return ['query' => $query, 'headers' => ['Severity', 'Service', 'Title', 'Status', 'First observed', 'Last observed'], 'map' => fn (MonitoringAlert $alert): array => [$alert->severity, $alert->service, $alert->title, $alert->status, $alert->first_observed_at?->toISOString(), $alert->last_observed_at?->toISOString()]];
        }
        return [];
    }
}
