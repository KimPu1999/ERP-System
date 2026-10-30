<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class SupplierPurchasePriceListItem extends Model {
    use HasUuids;
    protected $fillable=['tenant_id','price_list_id','supplier_product_id','minimum_quantity','maximum_quantity','unit_price','purchase_uom','tax_code'];
    protected function casts():array{return ['minimum_quantity'=>'decimal:6','maximum_quantity'=>'decimal:6','unit_price'=>'decimal:6'];}
    public function priceList(){return $this->belongsTo(SupplierPurchasePriceList::class,'price_list_id');}
    public function supplierProduct(){return $this->belongsTo(SupplierProduct::class);}
}
