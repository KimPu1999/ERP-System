<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class SupportMessage extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'ticket_id', 'author_id', 'author_type', 'visibility', 'body', 'attachments'];
    protected function casts(): array { return ['attachments' => 'array']; }

    public function ticket() { return $this->belongsTo(SupportTicket::class, 'ticket_id'); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
    public function uploads() { return $this->hasMany(SupportAttachment::class, 'message_id'); }
}
