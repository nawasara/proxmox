<?php

namespace Nawasara\Proxmox\Livewire\Node\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Proxmox\Models\ProxmoxNode;
use Nawasara\Proxmox\Models\ProxmoxVm;
use Nawasara\Proxmox\Repositories\ProxmoxNodeRepository;
use Nawasara\Sync\Models\SyncJob;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;

class Overview extends Component
{
    use HasBrowserToast;

    protected function repo(): ProxmoxNodeRepository
    {
        return new ProxmoxNodeRepository();
    }

    #[Computed]
    public function nodes()
    {
        return ProxmoxNode::orderBy('node_name')->get();
    }

    #[Computed]
    public function clusterTotals(): array
    {
        $nodes = $this->nodes;
        return [
            'nodes' => $nodes->count(),
            'nodes_online' => $nodes->where('status', 'online')->count(),
            'cpu_total' => (int) $nodes->sum('cpu_count'),
            'mem_total' => (int) $nodes->sum('mem_total'),
            'mem_used' => (int) $nodes->sum('mem_used'),
            'disk_total' => (int) $nodes->sum('disk_total'),
            'disk_used' => (int) $nodes->sum('disk_used'),
            'vm_count' => ProxmoxVm::count(),
            'vm_running' => ProxmoxVm::where('status', 'running')->count(),
        ];
    }

    #[Computed]
    public function lastSyncedAt(): ?string
    {
        $when = $this->repo()->lastSyncedAt();
        return $when ? $when->diffForHumans() : null;
    }

    /**
     * Whether any cluster-wide sync is currently queued or running.
     * Drives a fast wire:poll only while sync work is in flight.
     */
    #[Computed]
    public function isSyncing(): bool
    {
        return SyncJob::query()
            ->where('service', 'proxmox')
            ->whereIn('action', ['sync_nodes', 'sync_vms'])
            ->whereIn('status', [SyncJob::STATUS_QUEUED, SyncJob::STATUS_RUNNING])
            ->exists();
    }

    public function refresh(): void
    {
        Gate::authorize('proxmox.sync.execute');
        $this->repo()->syncNow();
        // Trigger VM sync juga sekalian
        \Nawasara\Proxmox\Jobs\SyncProxmoxVmsJob::dispatch(triggerSource: 'manual');
        $this->toastSuccess('Sync dispatched (nodes + VMs).');
    }

    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.node.section.overview');
    }
}
