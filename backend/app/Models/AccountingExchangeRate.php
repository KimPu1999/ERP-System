<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class AccountingExchangeRate extends Model { use HasUuids; protected $fillable=['tenant_id','from_currency_id','to_currency_id','rate_date','rate']; protected function casts():array{return ['rate_date'=>'date'];} }
