<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Company extends Model
{
    use HasUuids;
    protected $fillable = ['tenant_id', 'name', 'code', 'timezone', 'is_active', 'legal_name', 'trading_name', 'business_type', 'industry', 'registration_number', 'tax_identifier', 'tax_registration_details', 'registration_date', 'description', 'email', 'phone', 'alternative_phone', 'website', 'country_code', 'region', 'city', 'township', 'postal_code', 'street_address', 'logo_path', 'cover_image_path', 'base_currency', 'default_locale', 'date_format', 'fiscal_year_start', 'document_number_prefix', 'number_format', 'invoice_prefix', 'purchase_order_prefix', 'brand_color'];
    protected function casts(): array { return ['is_active' => 'boolean', 'registration_date' => 'date', 'fiscal_year_start' => 'date']; }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function branches() { return $this->hasMany(Branch::class); }
    public function documents() { return $this->hasMany(CompanyDocument::class); }
    public function memberships() { return $this->hasMany(CompanyMembership::class); }
    public function teams() { return $this->hasMany(Team::class); }
}
