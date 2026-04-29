<?php

namespace Nawasara\Proxmox\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nawasara\Proxmox\Jobs\SyncProxmoxVmsJob;
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
        // Lifecycle ops (start/stop/restart) ditambah di Day 3 sebagai mutation jobs
        throw new \BadMethodCallException('Pakai mutation job spesifik (start/stop/restart) ditambah di Day 3.');
    }

    public function delete(string|int $id): ?SyncJob
    {
        throw new \BadMethodCallException('Termination VM ditambah di Day 3 sebagai mutation job.');
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
