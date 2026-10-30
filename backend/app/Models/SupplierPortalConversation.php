<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SupplierPortalConversation extends Model { use HasUuids; protected $fillable=['tenant_id','supplier_id','company_id','subject','type','related_document_id','related_document_type','status']; public function messages(){return $this->hasMany(SupplierPortalMessage::class,'conversation_id');} }
