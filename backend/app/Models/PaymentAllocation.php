<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PaymentAllocation extends Model { use HasUuids; protected $fillable=['tenant_id','payment_id','invoice_id','amount']; }
