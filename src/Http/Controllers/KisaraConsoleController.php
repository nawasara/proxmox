<?php

namespace Nawasara\Proxmox\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Nawasara\Proxmox\Models\ConsoleSession;
use Nawasara\Proxmox\Services\KisaraConsole;

/**
 * Halaman console untuk PIC dari Akun Kisara, di host console tersendiri.
 *
 * Tidak memakai sesi panel — PIC tidak punya akun panel. Yang mengikat
 * halaman ini ke orangnya:
 *
 *   - tautan sekali pakai dari KisaraConsole::issue(), yang hanya diterbitkan
 *     setelah Akun Kisara memastikan orangnya PIC dan baru saja login ulang;
 *   - IP: bila Akun Kisara mengirim IP peminta, tautan hanya dapat dibuka dari
 *     IP yang sama (dibaca dari CF-Connecting-IP; keduanya di belakang
 *     Cloudflare, jadi sumbernya sama);
 *   - denyut dan penutupan sesi hanya diterima dari sesi peramban yang membuka
 *     halaman itu (id sesinya dicatat di session Laravel saat dibuka).
 */
class KisaraConsoleController extends Controller
{
    private const OPENED = 'kisara_console_sessions';

    public function show(Request $request, string $ticket)
    {
        // pull, bukan get: sekali pakai. Memuat ulang tab berarti meminta
        // tautan baru dari Akun Kisara.
        $cached = Cache::pull(KisaraConsole::CACHE_PREFIX.$ticket);

        if (! $cached) {
            return response()->view('nawasara-proxmox::console.expired', ['kisara' => true], 410);
        }

        $requestIp = $request->header('CF-Connecting-IP') ?: $request->ip();
        if (! empty($cached['client_ip']) && $requestIp && $cached['client_ip'] !== $requestIp) {
            // Tautannya sudah hangus (pull di atas) — orang yang memungutnya
            // tidak mendapat kesempatan kedua.
            return response()->view('nawasara-proxmox::console.expired', ['kisara' => true], 403);
        }

        $opened = (array) $request->session()->get(self::OPENED, []);
        $opened[] = (int) $cached['session_id'];
        $request->session()->put(self::OPENED, array_values(array_unique($opened)));

        return response()->view('nawasara-proxmox::console.show', [
            'wsUrl' => $cached['ws_url'],
            'termTicket' => $cached['term_ticket'],
            'consoleUser' => $cached['console_user'],
            'sessionId' => $cached['session_id'],
            'heartbeatUrl' => route('nawasara-proxmox.kisara-console.heartbeat', $cached['session_id']),
            'closeUrl' => route('nawasara-proxmox.kisara-console.close', $cached['session_id']),
            'userNip' => null,
            'userName' => $cached['user_name'] ?? null,
            // cmode=shell: console container langsung shell, tidak ada
            // perintah yang perlu diketikkan.
            'enterCommand' => null,
            'vmName' => $cached['vm_name'],
            'node' => $cached['node'],
            'vmid' => $cached['vmid'],
            'type' => $cached['type'],
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function heartbeat(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->ownedSession($request, $sessionId);

        if (! $session) {
            return response()->json(['ok' => false], 403);
        }

        if ($session->isOpen()) {
            $session->update(['last_seen_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    /** Durasi dihitung server dari started_at, bukan angka kiriman peramban. */
    public function close(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->ownedSession($request, $sessionId);

        if (! $session) {
            return response()->json(['ok' => false], 403);
        }

        if ($session->isOpen()) {
            $endedAt = now();
            $session->update([
                'ended_at' => $endedAt,
                'duration_seconds' => (int) $session->started_at->diffInSeconds($endedAt),
                'last_seen_at' => $endedAt,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function ownedSession(Request $request, int $sessionId): ?ConsoleSession
    {
        if (! in_array($sessionId, (array) $request->session()->get(self::OPENED, []), true)) {
            return null;
        }

        return ConsoleSession::where('id', $sessionId)->where('channel', KisaraConsole::CHANNEL)->first();
    }
}
