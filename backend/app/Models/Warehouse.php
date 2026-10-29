<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Warehouse extends Model { use HasUuids; protected $fillable = ['tenant_id', 'company_id', 'branch_id', 'name', 'code', 'is_active']; protected function casts(): array { return ['is_active' => 'boolean']; } public function locations() { return $this->hasMany(WarehouseLocation::class); } }
