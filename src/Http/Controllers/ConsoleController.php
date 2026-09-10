<?php

namespace Nawasara\Proxmox\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

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

        $kunci = 'proxmox:console:'.$ticket;
        $sesi = Cache::get($kunci);

        if (! $sesi) {
            // Kedaluwarsa, sudah dipakai di tab lain, atau memang tidak pernah
            // ada. Halaman penjelasan, bukan 404 — orang perlu tahu bahwa yang
            // harus dilakukan adalah membuka console lagi dari daftar VM.
            return response()->view('nawasara-proxmox::console.expired', [], 410);
        }

        // Tiket milik orang lain tidak boleh dipakai meski URL-nya diketahui.
        if (($sesi['user_id'] ?? null) !== auth()->id()) {
            abort(403, 'Tiket console ini bukan milik Anda.');
        }

        // Sekali pakai: memuat ulang tab berarti menerbitkan tiket baru dari
        // daftar VM, bukan memutar ulang sambungan lama.
        Cache::forget($kunci);

        return response()->view('nawasara-proxmox::console.show', [
            'wsUrl' => $sesi['ws_url'],
            'sessionTicket' => $sesi['session_ticket'],
            'vmName' => $sesi['vm_name'],
            'node' => $sesi['node'],
            'vmid' => $sesi['vmid'],
            'type' => $sesi['type'],
        ]);
    }
}
