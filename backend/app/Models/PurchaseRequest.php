<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class PurchaseRequest extends Model { use HasUuids; protected $fillable = ['tenant_id', 'requested_by', 'number', 'state', 'reason']; public function items() { return $this->hasMany(PurchaseRequestItem::class); } }
