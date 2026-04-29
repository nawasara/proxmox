<?php

namespace Nawasara\Proxmox\Jobs;

use Nawasara\Proxmox\Models\ProxmoxNode;
use Nawasara\Proxmox\Services\ProxmoxClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

class SyncProxmoxNodesJob extends AbstractSyncJob
{
    public int $timeout = 60;

    protected function service(): string
    {
        return 'proxmox';
    }

    protected function action(): string
    {
        return 'sync_nodes';
    }

    protected function targetType(): ?string
    {
        return 'ProxmoxNode';
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

        $nodes = $client->getNodes();

        $stats = [
            'total' => count($nodes),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
        ];

        foreach ($nodes as $row) {
            $nodeName = $row['node'] ?? null;
            if (! $nodeName) {
                continue;
            }

            // Optional: pull richer detail per node (kernel, pve version)
            $detail = $client->getNodeStatus($nodeName) ?? [];

            $attrs = [
                'node_name' => $nodeName,
                'status' => $row['status'] ?? 'unknown',
                'type' => $row['type'] ?? 'node',
                'cpu_count' => $row['maxcpu'] ?? null,
                'cpu_usage' => $row['cpu'] ?? null,
                'mem_total' => $row['maxmem'] ?? null,
                'mem_used' => $row['mem'] ?? null,
                'disk_total' => $row['maxdisk'] ?? null,
                'disk_used' => $row['disk'] ?? null,
                'uptime' => $row['uptime'] ?? null,
                'pve_version' => $detail['pveversion'] ?? ($row['level'] ?? null),
                'kernel_version' => $detail['kversion'] ?? null,
                'sync_status' => ProxmoxNode::SYNC_SYNCED,
                'sync_error' => null,
                'last_synced_at' => now(),
            ];

            $existing = ProxmoxNode::where('node_name', $nodeName)->first();

            if ($existing) {
                $tempModel = new ProxmoxNode(array_merge($existing->toArray(), $attrs));
                $newHash = $tempModel->computeContentHash();

                if ($existing->content_hash === $newHash && $existing->isSynced()) {
                    // Hash sama tapi tetap update last_synced_at + dynamic fields
                    $existing->update([
                        'cpu_usage' => $attrs['cpu_usage'],
                        'mem_used' => $attrs['mem_used'],
                        'disk_used' => $attrs['disk_used'],
                        'uptime' => $attrs['uptime'],
                        'last_synced_at' => now(),
                    ]);
                    $stats['unchanged']++;
                    continue;
                }

                $existing->update(array_merge($attrs, ['content_hash' => $newHash]));
                $stats['updated']++;
            } else {
                $tempModel = new ProxmoxNode($attrs);
                $newHash = $tempModel->computeContentHash();
                ProxmoxNode::create(array_merge($attrs, ['content_hash' => $newHash]));
                $stats['created']++;
            }
        }

        return $stats;
    }
}
