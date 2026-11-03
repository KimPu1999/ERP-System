<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformInvoiceItem extends Model
{
    use HasUuids;
    protected $fillable = ['tenant_id', 'invoice_id', 'description', 'quantity', 'unit_price', 'amount', 'currency_code', 'metadata'];
    protected function casts(): array { return ['quantity' => 'decimal:6', 'unit_price' => 'decimal:6', 'amount' => 'decimal:6', 'metadata' => 'array']; }
    public function invoice() { return $this->belongsTo(PlatformInvoice::class, 'invoice_id'); }
}
