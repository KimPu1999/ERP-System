<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Product extends Model { use HasUuids; protected $fillable = ['tenant_id', 'category_id', 'brand_id', 'name', 'slug', 'description', 'status']; public function variants() { return $this->hasMany(ProductVariant::class); } }
