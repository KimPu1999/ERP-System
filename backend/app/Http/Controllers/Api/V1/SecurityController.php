<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SecuritySetting;
use App\Services\{MfaService, SecurityEventLogger};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

final class SecurityController
{
    public function stepUp(Request $request, MfaService $mfa, SecurityEventLogger $events): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string'], 'code' => ['nullable', 'digits:6']]);
        $valid = Hash::check($data['password'], $request->user()->password);
        if ($valid && $request->user()->mfa_enabled_at) $valid = ! empty($data['code']) && $mfa->verify($mfa->decrypt($request->user()) ?? '', $data['code']);
        if (! $valid) {
            $events->record($request, 'auth.step_up_failed', $request->user()->id);
            return response()->json(['message' => 'Recent authentication failed.'], 422);
        }
        $configured = SecuritySetting::where('key', 'step_up_validity_minutes')->value('value');
        $minutes = (int) (is_array($configured) ? ($configured['value'] ?? 10) : 10);
        $minutes = max(1, min(60, $minutes));
        $validUntil = now()->addMinutes($minutes);
        $request->session()->put('security.step_up_until', $validUntil->timestamp);
        $events->record($request, 'auth.step_up_succeeded', $request->user()->id);
        return response()->json(['status' => 'verified', 'valid_until' => $validUntil->toISOString()]);
    }

    public function tokens(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->tokens()->latest()->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])->map(fn (PersonalAccessToken $token): array => ['id' => $token->id, 'name' => $token->name, 'abilities' => $token->abilities, 'last_used_at' => $token->last_used_at?->toISOString(), 'expires_at' => $token->expires_at?->toISOString(), 'created_at' => $token->created_at?->toISOString()])]);
    }

    public function revokeToken(Request $request, string $token): JsonResponse
    {
        $record = $request->user()->tokens()->whereKey($token)->first();
        abort_unless($record, 404, 'Token not found.');
        $record->delete();
        return response()->json(['status' => 'revoked']);
    }
}
