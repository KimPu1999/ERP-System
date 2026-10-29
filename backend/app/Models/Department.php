<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasUuids;
    protected $fillable = ['tenant_id', 'company_id', 'branch_id', 'name', 'code'];
}
