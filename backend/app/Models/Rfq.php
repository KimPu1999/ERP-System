<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Rfq extends Model { use HasUuids; protected $table = 'rfqs'; protected $fillable = ['tenant_id', 'purchase_request_id', 'number', 'state', 'quote_due_at']; protected function casts(): array { return ['quote_due_at' => 'date']; } public function suppliers() { return $this->belongsToMany(Supplier::class, 'rfq_suppliers'); } }
