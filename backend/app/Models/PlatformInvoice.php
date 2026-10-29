<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PlatformInvoice extends Model { use HasUuids; protected $fillable=['tenant_id','subscription_id','number','status','currency_code','subtotal','tax_total','total','paid_total','issued_on','due_on']; protected function casts():array{return ['subtotal'=>'decimal:6','tax_total'=>'decimal:6','total'=>'decimal:6','paid_total'=>'decimal:6','issued_on'=>'date','due_on'=>'date'];} }
