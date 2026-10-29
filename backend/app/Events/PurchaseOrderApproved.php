<?php
namespace App\Events;
use App\Models\PurchaseOrder;
final class PurchaseOrderApproved { public function __construct(public readonly PurchaseOrder $purchaseOrder) {} }
