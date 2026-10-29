<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SessionController
{
    public function index(Request $request): JsonResponse { $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity']); return response()->json(['data' => $sessions->map(fn ($session) => ['id' => $session->id, 'ip_address' => $session->ip_address, 'user_agent' => $session->user_agent, 'last_activity' => $session->last_activity, 'current' => hash_equals($session->id, $request->session()->getId())])]); }
    public function destroy(Request $request, string $session, SecurityEventLogger $events): JsonResponse { abort_if(hash_equals($request->session()->getId(), $session), 422, 'The current session cannot be revoked here.'); DB::table('sessions')->where('id', $session)->where('user_id', $request->user()->id)->delete(); $events->record($request, 'auth.session_revoked'); return response()->json(['status' => 'revoked']); }
}
