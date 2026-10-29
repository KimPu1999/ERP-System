<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class ProcurementApproval extends Model { use HasUuids; protected $table = 'procurement_approvals'; protected $fillable = ['tenant_id', 'document_type', 'document_id', 'approver_id', 'level', 'state', 'comment', 'decided_at']; protected function casts(): array { return ['decided_at' => 'datetime']; } }
