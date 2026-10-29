<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class FeatureFlag extends Model { use HasUuids; protected $fillable=['key','description','default_enabled']; protected function casts():array{return ['default_enabled'=>'boolean'];} }
