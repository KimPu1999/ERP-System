<?php
namespace App\Events;
use App\Models\PurchaseRequest;
final class PurchaseRequestApproved { public function __construct(public readonly PurchaseRequest $purchaseRequest) {} }
