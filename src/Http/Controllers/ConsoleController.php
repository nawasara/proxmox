<?php

namespace Nawasara\Proxmox\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Nawasara\Proxmox\Models\ConsoleSession;

/**
 * Halaman console layar penuh, dibuka di tab baru.
 *
 * Alurnya mengikuti nawasara/teleport, dengan alasan yang sama:
 *
 *   1. Livewire menerbitkan tiket lewat ProxmoxClient::createTermTicket()
 *   2. Info sambungan disimpan di cache dengan kunci acak, TTL pendek
 *   3. Browser membuka /nawasara-proxmox/console/{ticket}
 *   4. Controller ini menukarnya dengan info sambungan, lalu merender
 *      xterm.js yang menyambung langsung ke websocket Proxmox
 *
 * ⚠️ Alamat websocket TIDAK diletakkan di URL.
 *
 * Ia memuat tiket yang setara kunci masuk, dan URL tersimpan di riwayat
 * peramban, di log akses proxy, dan di header Referer. Kunci cache yang acak
 * membuat URL-nya tidak berarti apa-apa bila bocor — dan ia hangus setelah
 * sekali dipakai.
 */
class ConsoleController extends Controller
{
    public function show(Request $request, string $ticket)
    {
        Gate::authorize('proxmox.vm.console');

        $cacheKey = 'proxmox:console:'.$ticket;
        $cached = Cache::get($cacheKey);

        if (! $cached) {
            // Kedaluwarsa, sudah dipakai di tab lain, atau memang tidak pernah
            // ada. Halaman penjelasan, bukan 404 — orang perlu tahu bahwa yang
            // harus dilakukan adalah membuka console lagi dari daftar VM.
            return response()->view('nawasara-proxmox::console.expired', [], 410);
        }

        // Tiket milik orang lain tidak boleh dipakai meski URL-nya diketahui.
        if (($cached['user_id'] ?? null) !== auth()->id()) {
            abort(403, 'Tiket console ini bukan milik Anda.');
        }

        // Sekali pakai: memuat ulang tab berarti menerbitkan tiket baru dari
        // daftar VM, bukan memutar ulang sambungan lama.
        Cache::forget($cacheKey);

        return response()->view('nawasara-proxmox::console.show', [
            'wsUrl' => $cached['ws_url'],
            'sessionTicket' => $cached['session_ticket'],
            'consoleUser' => $cached['console_user'] ?? 'root@pam',
            'sessionId' => $cached['session_id'] ?? null,

            // Disiapkan, belum ada sumbernya: NIP tidak tersimpan di tabel
            // users maupun atribut Keycloak. Begitu diputuskan dari mana
            // asalnya, cukup isi di sini dan ia langsung tampil di bilah atas
            // console serta tercatat di riwayat akses.
            'userNip' => null,

            // Perintah masuk ke container, diketikkan setelah shell node siap.
            'enterCommand' => $cached['enter_command'] ?? null,
            'vmName' => $cached['vm_name'],
            'node' => $cached['node'],
            'vmid' => $cached['vmid'],
            'type' => $cached['type'],
        ]);
    }

    /**
     * Denyut nadi dari halaman console, sekali semenit.
     *
     * Tanpa ini, sesi yang tabnya ditutup paksa akan tercatat terbuka
     * selamanya dan durasinya terus bertambah — laporan akan menyatakan
     * seseorang berada di dalam mesin berhari-hari.
     */
    public function heartbeat(Request $request, int $sessionId): \Illuminate\Http\JsonResponse
    {
        $session = ConsoleSession::find($sessionId);

        // Hanya pemilik sesi yang boleh memperbaruinya. Tanpa pemeriksaan ini,
        // siapa pun yang menebak id dapat membuat sesi orang lain tampak hidup.
        if (! $session || $session->user_id !== auth()->id()) {
            return response()->json(['ok' => false], 403);
        }

        if ($session->isOpen()) {
            $session->update(['last_seen_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Tutup sesi — dipanggil saat tab ditutup atau sambungan berakhir.
     *
     * Durasinya dihitung di SERVER dari `started_at`, bukan dikirim browser.
     * Angka yang datang dari klien dapat dikarang, dan justru angka inilah
     * yang akan dibaca orang saat mempertanyakan sebuah akses.
     */
    public function close(Request $request, int $sessionId): \Illuminate\Http\JsonResponse
    {
        $session = ConsoleSession::find($sessionId);

        if (! $session || $session->user_id !== auth()->id()) {
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
}
