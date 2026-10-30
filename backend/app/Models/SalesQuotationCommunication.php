<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesQuotationCommunication extends Model { use HasUuids; protected $table='sales_quotation_communications'; protected $fillable=['tenant_id','quotation_id','sent_by','recipient','subject','state','queued_at','attempted_at','confirmed_at','failure_reason']; protected function casts():array{return ['queued_at'=>'datetime','attempted_at'=>'datetime','confirmed_at'=>'datetime'];} }
