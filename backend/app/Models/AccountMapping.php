<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class AccountMapping extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','mapping_key','account_id']; public function account(){return $this->belongsTo(ChartAccount::class,'account_id');} }
