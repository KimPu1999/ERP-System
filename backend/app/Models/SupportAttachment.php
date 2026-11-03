<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class SupportAttachment extends Model
{
    use HasUuids;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'ticket_id', 'message_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes'];

    public function ticket() { return $this->belongsTo(SupportTicket::class, 'ticket_id'); }
    public function message() { return $this->belongsTo(SupportMessage::class, 'message_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
