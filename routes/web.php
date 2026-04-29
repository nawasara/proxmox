<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Proxmox\Livewire\Node\Index as NodeIndex;
use Nawasara\Proxmox\Livewire\Vm\Index as VmIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('nawasara-proxmox')->group(function () {
    Route::get('vms', VmIndex::class)
        ->middleware(PermissionMiddleware::using('proxmox.vm.view'))
        ->name('nawasara-proxmox.vms.index');

    Route::get('nodes', NodeIndex::class)
        ->middleware(PermissionMiddleware::using('proxmox.node.view'))
        ->name('nawasara-proxmox.nodes.index');
});
