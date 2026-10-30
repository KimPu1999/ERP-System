<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class SupplierContract extends Model {
    use HasUuids;
    protected $fillable=['tenant_id','company_id','supplier_id','number','name','contract_type','currency_id','starts_at','ends_at','spend_ceiling','payment_terms','delivery_terms','renewal_terms','status','version','approval_request_id','notes','created_by','approved_by','activated_by','approved_at','activated_at'];
    protected function casts():array{return ['starts_at'=>'date','ends_at'=>'date','spend_ceiling'=>'decimal:6','approved_at'=>'datetime','activated_at'=>'datetime'];}
    public function supplier(){return $this->belongsTo(Supplier::class);}
    public function currency(){return $this->belongsTo(AccountingCurrency::class);}
    public function versions(){return $this->hasMany(SupplierContractVersion::class);}
}
