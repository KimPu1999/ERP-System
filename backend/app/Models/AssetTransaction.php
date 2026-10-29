<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class AssetTransaction extends Model { use HasUuids; protected $fillable=['tenant_id','asset_id','type','transaction_date','amount','journal_entry_id','created_by','description']; protected function casts():array{return ['transaction_date'=>'date','amount'=>'decimal:6'];} }
