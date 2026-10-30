<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesPriceList extends Model { use HasUuids; protected $table='sales_price_lists'; protected $fillable=['tenant_id','company_id','name','list_type','currency_id','customer_id','valid_from','valid_until','is_active']; protected function casts():array{return ['valid_from'=>'date','valid_until'=>'date','is_active'=>'boolean'];} public function items(){return $this->hasMany(SalesPriceListItem::class,'price_list_id');} }
