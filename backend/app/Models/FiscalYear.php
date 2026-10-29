<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class FiscalYear extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','name','starts_on','ends_on','state']; protected function casts():array{return ['starts_on'=>'date','ends_on'=>'date'];} public function periods(){return $this->hasMany(FiscalPeriod::class);} }
