<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierDocumentRequirement extends Model { use HasUuids; protected $fillable=['tenant_id','company_id','name','supplier_type','country','is_required','requires_expiry_date','requires_manual_verification','max_file_size_kb','allowed_mime_types','is_active','policy_version']; protected function casts():array{return ['is_required'=>'boolean','requires_expiry_date'=>'boolean','requires_manual_verification'=>'boolean','allowed_mime_types'=>'array','is_active'=>'boolean'];} }
