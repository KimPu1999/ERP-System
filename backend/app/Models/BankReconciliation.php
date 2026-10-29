<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class BankReconciliation extends Model { use HasUuids; protected $fillable=['tenant_id','bank_account_id','statement_date','statement_balance','book_balance','state','completed_by']; protected function casts():array{return ['statement_date'=>'date','statement_balance'=>'decimal:6','book_balance'=>'decimal:6'];} }
