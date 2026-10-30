<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class SupplierPurchasePriceList extends Model {
    use HasUuids;
    protected $fillable=['tenant_id','company_id','supplier_id','number','name','currency_id','effective_from','effective_to','status','version','description','approval_request_id','created_by','approved_by','activated_by','approved_at','activated_at'];
    protected function casts():array{return ['effective_from'=>'date','effective_to'=>'date','approved_at'=>'datetime','activated_at'=>'datetime'];}
    public function supplier(){return $this->belongsTo(Supplier::class);}
    public function currency(){return $this->belongsTo(AccountingCurrency::class);}
    public function items(){return $this->hasMany(SupplierPurchasePriceListItem::class,'price_list_id');}
}
