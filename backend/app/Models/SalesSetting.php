<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesSetting extends Model { use HasUuids; protected $fillable=['tenant_id','approval_required','invoice_on_order','invoice_on_delivery']; protected function casts():array{return ['approval_required'=>'boolean','invoice_on_order'=>'boolean','invoice_on_delivery'=>'boolean'];} }
