<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class SupplierQuotation extends Model { use HasUuids; protected $table = 'supplier_quotations'; protected $fillable = ['tenant_id', 'rfq_id', 'supplier_id', 'number', 'state', 'valid_until']; protected function casts(): array { return ['valid_until' => 'date']; } }
