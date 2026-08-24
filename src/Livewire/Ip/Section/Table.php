<?php

namespace Nawasara\Proxmox\Livewire\Ip\Section;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Proxmox\Models\ProxmoxIpAddress;
use Nawasara\Proxmox\Models\ProxmoxSubnet;
use Nawasara\Proxmox\Services\IpInventory;

/**
 * Daftar alamat IP yang diketahui terpakai.
 */
class Table extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $subnet = '';

    #[Url(except: '')]
    public string $source = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSubnet(): void
    {
        $this->resetPage();
    }

    public function updatedSource(): void
    {
        $this->resetPage();
    }

    /**
     * Alamat yang dipakai lebih dari satu NIC.
     *
     * Diambil sebagai himpunan sekali jalan, bukan diperiksa per baris —
     * memeriksa per baris berarti satu kueri tambahan untuk tiap baris yang
     * tampil.
     */
    #[Computed]
    public function conflictingIps(): array
    {
        return collect(app(IpInventory::class)->conflicts())
            ->pluck('ip')
            ->flip()
            ->all();
    }

    #[Computed]
    public function subnetOptions(): array
    {
        return ProxmoxSubnet::orderBy('cidr')
            ->pluck('cidr', 'id')
            ->all();
    }

    #[Computed]
    public function rows()
    {
        return ProxmoxIpAddress::query()
            ->with('subnet')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w
                    ->where('ip', 'like', $term)
                    ->orWhere('vm_name', 'like', $term)
                    ->orWhere('mac', 'like', $term)
                    ->orWhere('node_name', 'like', $term)
                    ->orWhere('vmid', 'like', $term));
            })
            ->when($this->subnet !== '', fn ($q) => $q->where('subnet_id', $this->subnet))
            ->when($this->source !== '', fn ($q) => $q->where('source', $this->source))
            ->ordered()
            ->paginate(25);
    }

    #[On('ip-inventory-changed')]
    public function refreshData(): void
    {
        unset($this->rows, $this->conflictingIps);
    }

    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.ip.section.table');
    }
}
