<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class BillingWebhookEvent extends Model { use HasUuids; protected $fillable=['provider','provider_event_id','event_type','payload','signature_verified','state','error','processed_at']; protected function casts():array{return ['payload'=>'array','signature_verified'=>'boolean','processed_at'=>'datetime'];} }
