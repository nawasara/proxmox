<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Proxmox\Livewire\Console\Credential as ConsoleCredentialPage;
use Nawasara\Proxmox\Livewire\Console\Index as ConsoleLogIndex;
use Nawasara\Proxmox\Livewire\Ip\Index as IpIndex;
use Nawasara\Proxmox\Livewire\Node\Index as NodeIndex;
use Nawasara\Proxmox\Http\Controllers\ConsoleController;
use Nawasara\Proxmox\Livewire\Vm\Index as VmIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('nawasara-proxmox')->group(function () {
    Route::get('vms', VmIndex::class)
        ->middleware(PermissionMiddleware::using('proxmox.vm.view'))
        ->name('nawasara-proxmox.vms.index');

    Route::get('nodes', NodeIndex::class)
        ->middleware(PermissionMiddleware::using('proxmox.node.view'))
        ->name('nawasara-proxmox.nodes.index');

    Route::get('ip-inventory', IpIndex::class)
        ->middleware(PermissionMiddleware::using('proxmox.ip.view'))
        ->name('nawasara-proxmox.ips.index');

    // Halaman console layar penuh, dibuka di tab baru.
    //
    // Kuncinya acak dan sekali pakai — ia menukar tiket yang tersimpan di
    // cache, bukan membawa alamat websocket di URL. Alamat itu memuat tiket
    // setara kunci masuk, dan URL tersimpan di riwayat peramban serta log
    // akses proxy.
    // Riwayat akses console. Ditaruh SEBELUM console/{ticket} supaya
    // 'history' tidak tertangkap sebagai tiket.
    Route::get('console-history', ConsoleLogIndex::class)
        ->middleware(PermissionMiddleware::using('proxmox.console.history'))
        ->name('nawasara-proxmox.console.history');

    Route::get('console-credentials', ConsoleCredentialPage::class)
        ->middleware(PermissionMiddleware::using('proxmox.console.credential'))
        ->name('nawasara-proxmox.console.credentials');

    Route::get('console/{ticket}', [ConsoleController::class, 'show'])
        ->middleware(PermissionMiddleware::using('proxmox.vm.console'))
        ->name('nawasara-proxmox.console');

    // Denyut nadi & penutupan sesi console. Keduanya memeriksa kepemilikan
    // sesi, sehingga id yang tertebak tidak dapat dipakai mengubah catatan
    // orang lain.
    Route::post('console/{sessionId}/heartbeat', [ConsoleController::class, 'heartbeat'])
        ->middleware(PermissionMiddleware::using('proxmox.vm.console'))
        ->name('nawasara-proxmox.console.heartbeat');

    Route::post('console/{sessionId}/close', [ConsoleController::class, 'close'])
        ->middleware(PermissionMiddleware::using('proxmox.vm.console'))
        ->name('nawasara-proxmox.console.close');
});
