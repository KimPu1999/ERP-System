<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

final class PlatformSettingsController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'authentication' => [
                'email_verification_required' => true,
                'mfa_capable' => class_exists(\App\Services\MfaService::class),
                'session_encrypted' => (bool) config('session.encrypt'),
                'session_secure_cookie' => (bool) config('session.secure'),
            ],
            'security' => [
                'debug_mode' => (bool) config('app.debug'),
                'platform_authorization' => 'server_enforced',
                'tenant_isolation' => 'database_context_and_server_authorization',
            ],
        ]]);
    }
}
