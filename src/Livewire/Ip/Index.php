<?php

namespace Nawasara\Proxmox\Livewire\Ip;

use Livewire\Component;

/**
 * Halaman Inventori IP — cangkang saja.
 *
 * Seluruh isi ada di section: ringkasan subnet, tabel alamat, dan daftar NIC
 * yang menunggu diisi. Lihat CLAUDE.md §1b.
 */
class Index extends Component
{
    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.ip.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
