<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class Supplier extends Model { use HasUuids; protected $fillable = ['tenant_id', 'name', 'code', 'email', 'phone', 'address', 'tax_identifier', 'status']; }
