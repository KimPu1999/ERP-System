<?php
namespace App\Events; use Illuminate\Foundation\Events\Dispatchable; use Illuminate\Queue\SerializesModels;
final class PosReceiptPosted { use Dispatchable,SerializesModels; public function __construct(public readonly string $tenantId,public readonly string $receiptId,public readonly string $companyId,public readonly string $total) {} }
