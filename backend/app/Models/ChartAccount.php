<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class ChartAccount extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','code','name','type','subtype','parent_id','description','normal_balance','is_control','posting_allowed','is_system','is_active','created_by','updated_by']; protected function casts():array{return ['is_control'=>'boolean','posting_allowed'=>'boolean','is_system'=>'boolean','is_active'=>'boolean'];} public function parent(){return $this->belongsTo(self::class,'parent_id');} public function children(){return $this->hasMany(self::class,'parent_id');} public function journalLines(){return $this->hasMany(JournalLine::class,'account_id');} }
