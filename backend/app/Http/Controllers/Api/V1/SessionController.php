<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SessionController
{
    public function index(Request $request): JsonResponse
    {
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity']);
        return response()->json(['data' => $sessions->map(fn ($session): array => ['id' => $this->opaqueId($session->id), 'ip_address' => $session->ip_address, 'user_agent' => mb_substr((string) $session->user_agent, 0, 240), 'last_activity' => date(DATE_ATOM, (int) $session->last_activity), 'current' => hash_equals($session->id, $request->session()->getId())])]);
    }

    public function destroy(Request $request, string $session, SecurityEventLogger $events): JsonResponse
    {
        $record = $this->findByOpaqueId($request, $session);
        abort_unless($record, 404, 'Session not found.');
        abort_if(hash_equals($request->session()->getId(), $record->id), 422, 'The current session cannot be revoked here.');
        DB::table('sessions')->where('id', $record->id)->delete();
        $events->record($request, 'auth.session_revoked');
        return response()->json(['status' => 'revoked']);
    }

    public function destroyOthers(Request $request, SecurityEventLogger $events): JsonResponse
    {
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        $events->record($request, 'auth.sessions_revoked');
        return response()->json(['status' => 'revoked']);
    }

    private function opaqueId(string $id): string { return hash_hmac('sha256', $id, (string) config('app.key')); }
    private function findByOpaqueId(Request $request, string $opaque): mixed
    {
        return DB::table('sessions')->where('user_id', $request->user()->id)->get(['id', 'ip_address', 'user_agent', 'last_activity'])->first(fn ($row): bool => hash_equals($this->opaqueId($row->id), $opaque));
    }
}
