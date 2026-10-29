<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class CreditNote extends Model { use HasUuids; protected $fillable=['tenant_id','customer_id','invoice_id','number','state','total']; }
