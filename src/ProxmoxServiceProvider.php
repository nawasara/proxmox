<?php

namespace Nawasara\Proxmox;

use Illuminate\Support\ServiceProvider;
use Nawasara\Proxmox\Services\ProxmoxClient;

class ProxmoxServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Routes / migrations / livewire registration ditambah saat
        // Phase 1 (inventory) dimulai. Skeleton sekarang hanya untuk
        // expose ProxmoxClient ke Vault test connection handler.
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-proxmox.php', 'nawasara-proxmox');
        $this->app->singleton(ProxmoxClient::class, fn () => new ProxmoxClient());
    }
}
