<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class JournalLine extends Model { use HasUuids; protected $fillable=['tenant_id','journal_entry_id','account_id','company_id','debit','credit','base_debit','base_credit','description']; protected function casts():array{return ['debit'=>'decimal:6','credit'=>'decimal:6','base_debit'=>'decimal:6','base_credit'=>'decimal:6'];} public function entry(){return $this->belongsTo(JournalEntry::class,'journal_entry_id');} public function account(){return $this->belongsTo(ChartAccount::class,'account_id');} }
