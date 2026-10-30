<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ProductCategory extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'parent_id', 'name', 'slug'];

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function products() { return $this->hasMany(Product::class, 'category_id'); }
}
