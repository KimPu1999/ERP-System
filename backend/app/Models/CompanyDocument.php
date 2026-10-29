<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CompanyDocument extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'company_id', 'uploaded_by', 'name', 'category', 'path', 'mime_type', 'size'];

    public function company() { return $this->belongsTo(Company::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
