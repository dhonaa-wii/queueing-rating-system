<?php

namespace App\Http\Controllers\Panelist;

use App\Http\Controllers\Controller;
use App\Models\TerminalAccessToken;
use App\Services\TerminalConnectionService;
use Illuminate\Http\Request;

/**
 * The panelist-side half of the QR join flow: a panelist scans the QR on a
 * room screen with their own already-logged-in phone, lands here
 * (role:PANELIST — `auth` sends them to /login first, then
 * redirect()->intended() brings them back), and confirms joining that room
 * as themselves. The room screen never sees the panelist's credentials.
 *
 * User-directed 2026-10-11: a QR is issued for the first free seat at the
 * time it is shown, but the seat is decided when the panelist confirms
 * (TerminalConnectionService::connectToRoom()). The token is then pointed at
 * the seat actually taken, which is how the device that showed the QR finds
 * and adopts the new connection (RoomSessionController::resolveConnection()).
 */
class TerminalScanController extends Controller
{
    public function show(Request $request, string $token)
    {
        $result = $this->resolveToken($token);

        if (! $result['ok']) {
            return view('panelist.terminal-scan.error', ['message' => $result['error']]);
        }

        return view('panelist.terminal-scan.confirm', [
            'terminal' => $result['terminal'],
            'token' => $token,
        ]);
    }

    public function claim(Request $request, string $token, TerminalConnectionService $service)
    {
        $result = $this->resolveToken($token);

        if (! $result['ok']) {
            return view('panelist.terminal-scan.error', ['message' => $result['error']]);
        }

        $terminal = $result['terminal'];
        $accessToken = $result['accessToken'];

        $outcome = $service->connectToRoom($terminal->roomSession, $request->user(), 'QR', $request->ip(), $request->userAgent(), $terminal);

        if (! $outcome['ok']) {
            return view('panelist.terminal-scan.error', ['message' => $outcome['error']]);
        }

        $accessToken->update([
            'used_at' => now(),
            'room_terminal_id' => $outcome['connection']->room_terminal_id,
        ]);

        return view('panelist.terminal-scan.connected', ['terminal' => $outcome['connection']->roomTerminal]);
    }

    private function resolveToken(string $token): array
    {
        $accessToken = TerminalAccessToken::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->with('roomTerminal.roomSession.presentationDateRoom.presentationDate.category')
            ->first();

        if (! $accessToken) {
            return ['ok' => false, 'error' => 'This QR code has expired or was already used. Scan the new one on the room screen.'];
        }

        return ['ok' => true, 'terminal' => $accessToken->roomTerminal, 'accessToken' => $accessToken];
    }
}
