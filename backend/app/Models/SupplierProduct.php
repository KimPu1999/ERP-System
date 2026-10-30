<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class SupplierProduct extends Model {
    use HasUuids;
    protected $fillable=['tenant_id','company_id','supplier_id','product_id','variant_id','catalog_reference','supplier_sku','supplier_name','description','supplier_barcode','brand','model_number','manufacturer_part_number','packaging_information','purchase_uom','minimum_order_quantity','order_multiple','pack_size','standard_lead_time','lead_time_unit','shipping_origin','availability_status','temporarily_unavailable_reason','expected_availability_date','discontinued_date','currency_id','base_purchase_price','price_effective_from','price_effective_to','tax_code','status','created_by','updated_by'];
    protected function casts():array{return ['minimum_order_quantity'=>'decimal:6','order_multiple'=>'decimal:6','pack_size'=>'decimal:6','base_purchase_price'=>'decimal:6','expected_availability_date'=>'date','discontinued_date'=>'date','price_effective_from'=>'date','price_effective_to'=>'date'];}
    public function supplier(){return $this->belongsTo(Supplier::class);}
    public function product(){return $this->belongsTo(Product::class);}
    public function variant(){return $this->belongsTo(ProductVariant::class);}
    public function currency(){return $this->belongsTo(AccountingCurrency::class);}
    public function priceListItems(){return $this->hasMany(SupplierPurchasePriceListItem::class);}
}
