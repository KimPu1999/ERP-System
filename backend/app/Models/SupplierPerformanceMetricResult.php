<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierPerformanceMetricResult extends Model { use HasUuids; protected $fillable=['tenant_id','evaluation_id','metric','score','numerator','denominator','sample_size','available','methodology','source_references']; protected function casts():array{return ['score'=>'decimal:4','numerator'=>'decimal:6','denominator'=>'decimal:6','available'=>'boolean','source_references'=>'array'];} public function evaluation(){return $this->belongsTo(SupplierPerformanceEvaluation::class,'evaluation_id');} }
