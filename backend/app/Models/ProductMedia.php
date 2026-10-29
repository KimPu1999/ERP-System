<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ProductMedia extends Model { use HasUuids; protected $table = 'product_media'; protected $fillable = ['tenant_id', 'product_id', 'variant_id', 'path', 'mime_type', 'kind']; }
