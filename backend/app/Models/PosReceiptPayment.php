<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PosReceiptPayment extends Model { use HasUuids; protected $fillable=['tenant_id','receipt_id','method','amount','state','reference']; protected function casts():array{return ['amount'=>'decimal:6'];} }
