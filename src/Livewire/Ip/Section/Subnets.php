<?php

namespace Nawasara\Proxmox\Livewire\Ip\Section;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\Proxmox\Models\ProxmoxSubnet;
use Nawasara\Proxmox\Services\IpInventory;

/**
 * Ringkasan tiap kolam IP, beserta alamat yang tampak bebas.
 *
 * ⚠️ Jumlah NIC tak dikenal ditampilkan MENYATU dengan angka bebas, bukan di
 * halaman lain. Sebuah alamat yang tampak bebas dapat saja sedang dipakai VM
 * yang tidak terlacak, dan menyembunyikan peringatan itu membuat angkanya
 * terbaca lebih pasti daripada yang sebenarnya.
 */
class Subnets extends Component
{
    /** Subnet yang sedang dibuka daftar alamat bebasnya. */
    public ?int $expandedId = null;

    #[Computed]
    public function summaries(): array
    {
        $inventory = app(IpInventory::class);

        return ProxmoxSubnet::query()
            // Publik lebih dulu — itu yang paling langka dan paling perlu
            // dijaga. /27 dengan 30 alamat habis jauh sebelum /24.
            ->orderByDesc('is_public')
            ->orderBy('cidr')
            ->get()
            ->map(fn (ProxmoxSubnet $s) => $inventory->summarise($s))
            ->all();
    }

    #[Computed]
    public function freeAddresses(): array
    {
        if (! $this->expandedId) {
            return [];
        }

        $subnet = ProxmoxSubnet::find($this->expandedId);

        return $subnet ? app(IpInventory::class)->freeAddresses($subnet, 60) : [];
    }

    public function toggle(int $subnetId): void
    {
        $this->expandedId = $this->expandedId === $subnetId ? null : $subnetId;
    }

    /** Dipanggil setelah isian manual tersimpan — angkanya harus ikut berubah. */
    #[On('ip-inventory-changed')]
    public function refreshData(): void
    {
        unset($this->summaries, $this->freeAddresses);
    }

    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.ip.section.subnets');
    }
}
