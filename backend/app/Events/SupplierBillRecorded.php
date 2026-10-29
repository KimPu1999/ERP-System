<?php
namespace App\Events;
use App\Models\SupplierBill;
final class SupplierBillRecorded { public function __construct(public readonly SupplierBill $supplierBill) {} }
