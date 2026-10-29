<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class JournalEntry extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','fiscal_period_id','currency_id','reversal_of_id','created_by','number','entry_date','state','source_type','source_id','idempotency_key','exchange_rate','description','posted_at']; protected function casts():array{return ['entry_date'=>'date','exchange_rate'=>'decimal:12','posted_at'=>'datetime'];} public function lines(){return $this->hasMany(JournalLine::class);} public function period(){return $this->belongsTo(FiscalPeriod::class,'fiscal_period_id');} }
