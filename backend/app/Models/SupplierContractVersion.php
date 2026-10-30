<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class SupplierContractVersion extends Model {
    use HasUuids;
    protected $fillable=['tenant_id','supplier_contract_id','version','snapshot','created_by'];
    protected function casts():array{return ['snapshot'=>'array'];}
    public function contract(){return $this->belongsTo(SupplierContract::class,'supplier_contract_id');}
}
