<?php
namespace App\Events;
use App\Models\GoodsReceipt;
final class GoodsReceiptPosted { public function __construct(public readonly GoodsReceipt $goodsReceipt) {} }
