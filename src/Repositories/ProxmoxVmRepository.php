<?php

namespace Nawasara\Proxmox\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nawasara\Proxmox\Jobs\SyncProxmoxVmsJob;
use Nawasara\Proxmox\Jobs\Vm\RestartProxmoxVmJob;
use Nawasara\Proxmox\Jobs\Vm\ShutdownProxmoxVmJob;
use Nawasara\Proxmox\Jobs\Vm\StartProxmoxVmJob;
use Nawasara\Proxmox\Jobs\Vm\StopProxmoxVmJob;
use Nawasara\Proxmox\Models\ProxmoxVm;
use Nawasara\Sync\Concerns\TracksLastSync;
use Nawasara\Sync\Contracts\SyncedRepository;
use Nawasara\Sync\Models\SyncJob;

class ProxmoxVmRepository implements SyncedRepository
{
    use TracksLastSync;

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)
            ->orderBy('node_name')
            ->orderBy('vmid')
            ->paginate($perPage);
    }

    public function find(string|int $id): ?Model
    {
        return ProxmoxVm::find($id);
    }

    public function all(array $filters = []): Collection
    {
        return $this->query($filters)->orderBy('vmid')->get();
    }

    public function create(array $data): ?SyncJob
    {
        throw new \BadMethodCallException('VM creation tidak di-support dari Nawasara — buat di Proxmox UI dulu, baru sync.');
    }

    public function update(string|int $id, array $data): ?SyncJob
    {
        throw new \BadMethodCallException('Use specific lifecycle methods: start/stop/shutdown/restart.');
    }

    public function delete(string|int $id): ?SyncJob
    {
        throw new \BadMethodCallException('VM destroy is not supported from Nawasara — perform in Proxmox UI.');
    }

    /**
     * Hard power-on. Returns the SyncJob tracker row.
     */
    public function start(ProxmoxVm $vm): ?SyncJob
    {
        return $this->dispatchVmJob(StartProxmoxVmJob::class, $vm);
    }

    /**
     * Hard stop (cuts power, no graceful shutdown).
     */
    public function stop(ProxmoxVm $vm): ?SyncJob
    {
        return $this->dispatchVmJob(StopProxmoxVmJob::class, $vm);
    }

    /**
     * Graceful shutdown — needs guest agent (qemu) or container init (lxc).
     */
    public function shutdown(ProxmoxVm $vm): ?SyncJob
    {
        return $this->dispatchVmJob(ShutdownProxmoxVmJob::class, $vm);
    }

    /**
     * Graceful reboot.
     */
    public function restart(ProxmoxVm $vm): ?SyncJob
    {
        return $this->dispatchVmJob(RestartProxmoxVmJob::class, $vm);
    }

    protected function dispatchVmJob(string $jobClass, ProxmoxVm $vm): ?SyncJob
    {
        $payload = [
            'node' => $vm->node_name,
            'vmid' => $vm->vmid,
            'vm_type' => $vm->vm_type,
            'name' => $vm->name,
        ];

        $jobClass::dispatch(payload: $payload, triggerSource: 'manual');

        return SyncJob::query()
            ->where('service', 'proxmox')
            ->where('target_type', 'ProxmoxVm')
            ->where('target_id', $vm->vm_type.':'.$vm->vmid)
            ->latest('id')
            ->first();
    }

    public function syncNow(): ?SyncJob
    {
        SyncProxmoxVmsJob::dispatch(triggerSource: 'manual');
        return SyncJob::query()
            ->where('service', 'proxmox')
            ->where('action', 'sync_vms')
            ->latest('id')
            ->first();
    }

    public function lastSyncedAt(): ?Carbon
    {
        return $this->lastSuccessfulSyncAt('proxmox', 'sync_vms');
    }

    protected function query(array $filters = [])
    {
        return ProxmoxVm::query()
            ->search($filters['search'] ?? null)
            ->ofType($filters['type'] ?? null)
            ->onNode($filters['node'] ?? null)
            ->when(($filters['status'] ?? null), fn ($q, $s) => $q->where('status', $s))
            ->when(($filters['template'] ?? null) === 'only', fn ($q) => $q->where('template', true))
            ->when(($filters['template'] ?? null) === 'hide', fn ($q) => $q->where('template', false));
    }
}
