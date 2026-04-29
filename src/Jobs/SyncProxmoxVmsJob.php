<?php

namespace Nawasara\Proxmox\Jobs;

use Nawasara\Proxmox\Models\ProxmoxVm;
use Nawasara\Proxmox\Services\ProxmoxClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

/**
 * Sync semua VM (qemu) + container (lxc) dari cluster ke DB snapshot.
 *
 * Pakai endpoint /cluster/resources?type=vm — one-shot fetch yang return
 * semua VM/CT cross-node beserta status + resource usage. Jauh lebih
 * efisien daripada loop per-node.
 */
class SyncProxmoxVmsJob extends AbstractSyncJob
{
    public int $timeout = 120;

    protected function service(): string
    {
        return 'proxmox';
    }

    protected function action(): string
    {
        return 'sync_vms';
    }

    protected function targetType(): ?string
    {
        return 'ProxmoxVm';
    }

    protected function targetId(): ?string
    {
        return null;
    }

    protected function execute(): array
    {
        $client = app(ProxmoxClient::class);

        if (! $client->isConfigured()) {
            throw new \RuntimeException('Proxmox client is not configured');
        }

        $rows = $client->getClusterVms();

        $stats = [
            'total' => count($rows),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'deactivated' => 0,
        ];

        $seen = []; // composite key vmid|vm_type

        foreach ($rows as $row) {
            $vmid = $row['vmid'] ?? null;
            $type = $row['type'] ?? null;
            if (! $vmid || ! in_array($type, ['qemu', 'lxc'], true)) {
                continue;
            }

            $seen[] = "{$vmid}|{$type}";

            $attrs = [
                'vmid' => (int) $vmid,
                'node_name' => $row['node'] ?? '',
                'vm_type' => $type,
                'name' => $row['name'] ?? null,
                'status' => $row['status'] ?? 'unknown',
                'lock' => $row['lock'] ?? null,
                'template' => (bool) ($row['template'] ?? false),
                'cpu_count' => $row['maxcpu'] ?? null,
                'cpu_usage' => $row['cpu'] ?? null,
                'mem_total' => $row['maxmem'] ?? null,
                'mem_used' => $row['mem'] ?? null,
                'disk_total' => $row['maxdisk'] ?? null,
                'disk_used' => $row['disk'] ?? null,
                'uptime' => $row['uptime'] ?? null,
                'tags' => isset($row['tags'])
                    ? array_values(array_filter(explode(';', (string) $row['tags'])))
                    : null,
                'sync_status' => ProxmoxVm::SYNC_SYNCED,
                'sync_error' => null,
                'last_synced_at' => now(),
            ];

            $existing = ProxmoxVm::where('vmid', $vmid)->where('vm_type', $type)->first();

            if ($existing) {
                $tempModel = new ProxmoxVm(array_merge($existing->toArray(), $attrs));
                $newHash = $tempModel->computeContentHash();

                if ($existing->content_hash === $newHash && $existing->isSynced()) {
                    // Hash sama → cuma update field dynamic + last_synced_at
                    $existing->update([
                        'cpu_usage' => $attrs['cpu_usage'],
                        'mem_used' => $attrs['mem_used'],
                        'disk_used' => $attrs['disk_used'],
                        'uptime' => $attrs['uptime'],
                        'status' => $attrs['status'],
                        'lock' => $attrs['lock'],
                        'last_synced_at' => now(),
                    ]);
                    $stats['unchanged']++;
                    continue;
                }

                $existing->update(array_merge($attrs, ['content_hash' => $newHash]));
                $stats['updated']++;
            } else {
                $tempModel = new ProxmoxVm($attrs);
                $newHash = $tempModel->computeContentHash();
                ProxmoxVm::create(array_merge($attrs, ['content_hash' => $newHash]));
                $stats['created']++;
            }
        }

        // VM yang hilang dari cluster (e.g. terminated) — hard delete
        // setelah confirm tidak ada di seen list
        if (! empty($seen)) {
            $stats['deactivated'] = ProxmoxVm::query()
                ->whereNotIn(\DB::raw('CONCAT(vmid, "|", vm_type)'), $seen)
                ->where('sync_status', '!=', ProxmoxVm::SYNC_PENDING_DELETE)
                ->where('sync_status', '!=', ProxmoxVm::SYNC_PENDING_CREATE)
                ->delete();
        }

        return $stats;
    }
}
