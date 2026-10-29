<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ProductCategory extends Model { use HasUuids; protected $fillable = ['tenant_id', 'parent_id', 'name', 'slug']; }
