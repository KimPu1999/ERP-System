<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class FiscalPeriod extends Model { use HasUuids; protected $fillable=['tenant_id','fiscal_year_id','name','starts_on','ends_on','state','closed_at','closed_by']; protected function casts():array{return ['starts_on'=>'date','ends_on'=>'date','closed_at'=>'datetime'];} public function fiscalYear(){return $this->belongsTo(FiscalYear::class,'fiscal_year_id');} }
