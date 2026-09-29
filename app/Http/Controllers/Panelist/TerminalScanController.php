<?php

namespace App\Http\Controllers\Panelist;

use App\Http\Controllers\Controller;
use App\Models\TerminalAccessToken;
use App\Services\TerminalConnectionService;
use Illuminate\Http\Request;

/**
 * The panelist-side half of the QR terminal join flow (user-directed
 * 2026-08-15 correction): a panelist scans a tablet's QR code with their own
 * already-logged-in phone, lands here (role:PANELIST — the `auth` middleware
 * sends them to /login first if needed, then LoginController's
 * redirect()->intended() brings them straight back to this exact URL), and
 * confirms claiming that terminal seat as themselves. The tablet itself
 * never sees or handles the panelist's personal credentials.
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

        $outcome = $service->connect($terminal, $request->user(), 'QR', $request->ip(), $request->userAgent());

        if (! $outcome['ok']) {
            return view('panelist.terminal-scan.error', ['message' => $outcome['error']]);
        }

        $accessToken->update(['used_at' => now()]);

        return view('panelist.terminal-scan.connected', ['terminal' => $terminal]);
    }

    private function resolveToken(string $token): array
    {
        $accessToken = TerminalAccessToken::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->with('roomTerminal.terminalType', 'roomTerminal.roomSession.presentationDateRoom')
            ->first();

        if (! $accessToken) {
            return ['ok' => false, 'error' => 'This QR code has expired or was already used — ask the Administrator to refresh it on the tablet.'];
        }

        return ['ok' => true, 'terminal' => $accessToken->roomTerminal, 'accessToken' => $accessToken];
    }
}
