<?php
namespace App\Events; use Illuminate\Foundation\Events\Dispatchable; use Illuminate\Queue\SerializesModels;
final class FixedAssetCapitalized { use Dispatchable,SerializesModels; public function __construct(public readonly string $tenantId,public readonly string $assetId,public readonly string $companyId,public readonly string $amount) {} }
