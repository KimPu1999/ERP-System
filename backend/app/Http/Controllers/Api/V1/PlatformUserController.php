<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PlatformUserController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->where('is_platform_super_admin', true)->latest();
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($builder) => $builder->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"));
        }
        if ($status = $request->query('status')) $query->where('status', $status);
        $users = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));
        $users->through(fn (User $user): array => $this->payload($user));
        return response()->json(['data' => $users, 'summary' => [
            'total' => User::where('is_platform_super_admin', true)->count(),
            'active' => User::where('is_platform_super_admin', true)->where('status', 'active')->whereNull('suspended_at')->count(),
        ]]);
    }

    public function changeStatus(Request $request, User $user, AuditLogger $audit): JsonResponse
    {
        abort_unless($user->is_platform_super_admin === true, 404);
        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);
        abort_if($user->is($request->user()) && $data['status'] === 'suspended', 422, 'You cannot suspend your own platform account.');
        if ($data['status'] === 'suspended') {
            $activeAdmins = User::where('is_platform_super_admin', true)->where('status', 'active')->whereNull('suspended_at')->count();
            abort_if($activeAdmins <= 1, 422, 'The last active platform administrator cannot be suspended.');
        }
        $before = $this->payload($user);
        $user->update(['status' => $data['status'], 'suspended_at' => $data['status'] === 'suspended' ? now() : null]);
        if ($data['status'] === 'suspended') {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $audit->record($request, 'platform.user.status_changed', null, $user, ['before' => $before, 'after' => $this->payload($user)]);
        return response()->json(['data' => $this->payload($user->fresh())]);
    }

    private function payload(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'status', 'email_verified_at', 'mfa_enabled_at', 'last_login_at', 'is_platform_super_admin']) + ['suspended_at' => $user->suspended_at?->toISOString()];
    }
}
