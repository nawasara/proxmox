<?php

namespace Nawasara\Proxmox\Livewire\Ip\Section;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Proxmox\Models\ProxmoxIpAddress;
use Nawasara\Proxmox\Models\ProxmoxSubnet;
use Nawasara\Proxmox\Models\ProxmoxUnknownNic;

/**
 * NIC yang IP-nya belum diketahui, beserta form pengisiannya.
 *
 * Inilah satu-satunya cara mengetahui alamat VM QEMU di sini: config Proxmox
 * tidak menyimpannya, guest agent tidak terpasang, dan `/execute` — jalan
 * membaca tabel ARP node — menolak token API karena menuntut root@pam.
 *
 * MAC dan bridge ditampilkan sebagai penuntun: keduanya diketahui Proxmox,
 * dan bridge menyempitkan pilihan ke satu subnet sehingga pengisi tidak
 * menebak dari nol.
 */
class UnknownNics extends Component
{
    /** NIC yang sedang diisi. Null berarti form tertutup. */
    public ?int $editingId = null;

    public ?string $ip = null;
    public ?string $note = null;

    #[Computed]
    public function nics()
    {
        return ProxmoxUnknownNic::query()
            ->with('suggestedSubnet')
            ->orderBy('node_name')
            ->orderBy('vmid')
            ->orderBy('nic')
            ->get();
    }

    #[Computed]
    public function editing(): ?ProxmoxUnknownNic
    {
        return $this->editingId
            ? ProxmoxUnknownNic::with('suggestedSubnet')->find($this->editingId)
            : null;
    }

    /**
     * Alamat bebas di subnet yang ditunjuk bridge NIC ini.
     *
     * Ditawarkan supaya pengisi memilih, bukan mengarang. Daftarnya tetap
     * sekadar saran — ia belum memperhitungkan NIC lain yang juga belum
     * diisi.
     */
    #[Computed]
    public function suggestions(): array
    {
        $subnet = $this->editing?->suggestedSubnet;

        if (! $subnet) {
            return [];
        }

        return app(\Nawasara\Proxmox\Services\IpInventory::class)
            ->freeAddresses($subnet, 12);
    }

    public function edit(int $id): void
    {
        $this->authorize('proxmox.ip.edit');

        $this->editingId = $id;
        $this->ip = null;
        $this->note = null;
        $this->resetErrorBag();

        $this->dispatch('modal-open:isi-ip');
    }

    public function use(string $ip): void
    {
        $this->ip = $ip;
    }

    public function save(): void
    {
        $this->authorize('proxmox.ip.edit');

        $nic = $this->editing;

        if (! $nic) {
            $this->dispatch('modal-close:isi-ip');

            return;
        }

        $this->validate([
            'ip' => [
                'required',
                'ipv4',
                // Alamat yang sama pada NIC yang sama tidak boleh ganda.
                // Alamat sama di VM BERBEDA sengaja diizinkan — itu bentrok,
                // dan inventori harus menunjukkannya, bukan menolak
                // mencatatnya.
                Rule::unique('nawasara_proxmox_ip_addresses', 'ip')
                    ->where('vmid', $nic->vmid)
                    ->where('nic', $nic->nic),
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'ip.required' => 'Alamat IP wajib diisi.',
            'ip.ipv4' => 'Format alamat IPv4 tidak sah.',
            'ip.unique' => 'Alamat ini sudah tercatat untuk NIC yang sama.',
        ]);

        $subnet = $this->subnetFor($this->ip);

        // Peringatan, BUKAN penolakan. Alamat di luar subnet bridge-nya
        // memang mencurigakan, tetapi jaringan sungguhan punya pengecualian —
        // dan menolaknya berarti memaksa orang mencatat di luar sistem, yang
        // justru membuat inventori ini kehilangan gunanya.
        $peringatan = null;

        if ($nic->suggestedSubnet && ! $nic->suggestedSubnet->contains($this->ip)) {
            $peringatan = 'Tersimpan, tetapi alamat ini di luar '
                .$nic->suggestedSubnet->cidr.' — subnet yang biasa dipakai '
                .($nic->bridge ?? 'bridge ini').'. Mohon dipastikan.';
        }

        ProxmoxIpAddress::create([
            'ip' => $this->ip,
            'subnet_id' => $subnet?->id,
            'vm_id' => $nic->vm_id,
            'vmid' => $nic->vmid,
            'vm_name' => $nic->vm_name,
            'node_name' => $nic->node_name,
            'nic' => $nic->nic,
            'mac' => $nic->mac,
            'bridge' => $nic->bridge,
            'source' => ProxmoxIpAddress::SOURCE_MANUAL,
            'filled_by' => auth()->user()?->name,
            'note' => $this->note,
            'last_seen_at' => now(),
        ]);

        // NIC-nya keluar dari daftar tunggu — sudah diketahui sekarang.
        $nic->delete();

        $this->editingId = null;
        $this->ip = null;
        $this->note = null;

        $this->dispatch('modal-close:isi-ip');
        $this->dispatch('ip-inventory-changed');

        $this->dispatch('toast',
            type: $peringatan ? 'warning' : 'success',
            message: $peringatan ?? 'Alamat IP tersimpan.',
        );
    }

    protected function subnetFor(string $ip): ?ProxmoxSubnet
    {
        foreach (ProxmoxSubnet::all() as $subnet) {
            if ($subnet->contains($ip)) {
                return $subnet;
            }
        }

        return null;
    }

    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.ip.section.unknown-nics');
    }
}
