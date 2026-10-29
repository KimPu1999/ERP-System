<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class AssetCategory extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','name','code','asset_account_id','depreciation_account_id']; }
