<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AccountingIntegrationSetting extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'company_id', 'automatic_posting_enabled', 'enabled_at', 'enabled_by'];

    protected function casts(): array
    {
        return ['automatic_posting_enabled' => 'boolean', 'enabled_at' => 'datetime'];
    }
}
