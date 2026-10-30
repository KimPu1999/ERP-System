<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SalesPriceListItem extends Model { use HasUuids; protected $table='sales_price_list_items'; protected $fillable=['tenant_id','price_list_id','variant_id','minimum_quantity','unit_price','valid_from','valid_until']; protected function casts():array{return ['minimum_quantity'=>'decimal:6','unit_price'=>'decimal:6','valid_from'=>'date','valid_until'=>'date'];} }
