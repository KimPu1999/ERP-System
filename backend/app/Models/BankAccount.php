<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class BankAccount extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','currency_id','ledger_account_id','name','account_number','status']; public function currency(){return $this->belongsTo(AccountingCurrency::class,'currency_id');} public function ledgerAccount(){return $this->belongsTo(ChartAccount::class,'ledger_account_id');} }
