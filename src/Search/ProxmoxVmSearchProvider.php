<?php

namespace Nawasara\Proxmox\Search;

use Nawasara\Proxmox\Models\ProxmoxVm;
use Nawasara\Search\Contracts\SearchProvider;

class ProxmoxVmSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'proxmox-vm';
    }

    public function label(): string
    {
        return 'Proxmox VM';
    }

    public function permission(): ?string
    {
        return 'proxmox.vm.view';
    }

    public function search(string $term, int $limit): array
    {
        return ProxmoxVm::query()
            ->search($term)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'vmid'])
            ->map(fn (ProxmoxVm $vm) => [
                'label' => $vm->name,
                'sublabel' => 'VMID '.$vm->vmid,
                'url' => url('nawasara-proxmox/vms?search='.urlencode($term)),
            ])
            ->all();
    }
}
