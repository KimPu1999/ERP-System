<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class StockCount extends Model { use HasUuids; protected $fillable = ['tenant_id', 'location_id', 'created_by', 'status', 'posted_at']; protected function casts(): array { return ['posted_at' => 'datetime']; } public function lines() { return $this->hasMany(StockCountLine::class, 'count_id'); } }
