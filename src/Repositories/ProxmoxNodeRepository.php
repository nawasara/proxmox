<?php

namespace Nawasara\Proxmox\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nawasara\Proxmox\Jobs\SyncProxmoxNodesJob;
use Nawasara\Proxmox\Models\ProxmoxNode;
use Nawasara\Sync\Concerns\TracksLastSync;
use Nawasara\Sync\Contracts\SyncedRepository;
use Nawasara\Sync\Models\SyncJob;

class ProxmoxNodeRepository implements SyncedRepository
{
    use TracksLastSync;

    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return ProxmoxNode::query()
            ->search($filters['search'] ?? null)
            ->orderBy('node_name')
            ->paginate($perPage);
    }

    public function find(string|int $id): ?Model
    {
        return ProxmoxNode::find($id);
    }

    public function all(array $filters = []): Collection
    {
        return ProxmoxNode::orderBy('node_name')->get();
    }

    public function create(array $data): ?SyncJob
    {
        throw new \BadMethodCallException('Nodes are managed via Proxmox cluster operations, not Nawasara.');
    }

    public function update(string|int $id, array $data): ?SyncJob
    {
        throw new \BadMethodCallException('Nodes are read-only mirror.');
    }

    public function delete(string|int $id): ?SyncJob
    {
        throw new \BadMethodCallException('Nodes are read-only mirror.');
    }

    public function syncNow(): ?SyncJob
    {
        SyncProxmoxNodesJob::dispatch(triggerSource: 'manual');
        return SyncJob::query()
            ->where('service', 'proxmox')
            ->where('action', 'sync_nodes')
            ->latest('id')
            ->first();
    }

    public function lastSyncedAt(): ?Carbon
    {
        return $this->lastSuccessfulSyncAt('proxmox', 'sync_nodes');
    }
}
