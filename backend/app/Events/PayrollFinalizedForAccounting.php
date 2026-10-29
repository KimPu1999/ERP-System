<?php
namespace App\Events; use Illuminate\Foundation\Events\Dispatchable; use Illuminate\Queue\SerializesModels;
final class PayrollFinalizedForAccounting { use Dispatchable,SerializesModels; public function __construct(public readonly string $tenantId,public readonly string $payrollRunId,public readonly string $companyId) {} }
