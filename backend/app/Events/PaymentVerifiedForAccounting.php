<?php
namespace App\Events;
use Illuminate\Foundation\Events\Dispatchable; use Illuminate\Queue\SerializesModels;
final class PaymentVerifiedForAccounting { use Dispatchable,SerializesModels; public function __construct(public readonly string $tenantId,public readonly string $paymentId,public readonly string $customerId,public readonly string $amount) {} }
