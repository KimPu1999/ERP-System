<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ProcurementDocumentHistory extends Model { use HasUuids; protected $table = 'procurement_document_history'; protected $fillable = ['tenant_id', 'document_type', 'document_id', 'actor_id', 'from_state', 'to_state', 'comment']; }
