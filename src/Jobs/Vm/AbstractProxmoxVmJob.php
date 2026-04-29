<?php

namespace Nawasara\Proxmox\Jobs\Vm;

use Nawasara\Proxmox\Models\ProxmoxVm;
use Nawasara\Proxmox\Services\ProxmoxClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

/**
 * Base for VM lifecycle/snapshot jobs.
 *
 * Required payload keys: node, vmid, vm_type ('qemu'|'lxc').
 */
abstract class AbstractProxmoxVmJob extends AbstractSyncJob
{
    public int $timeout = 120;

    protected function service(): string
    {
        return 'proxmox';
    }

    protected function targetType(): ?string
    {
        return 'ProxmoxVm';
    }

    protected function targetId(): ?string
    {
        $vmid = $this->payload['vmid'] ?? null;
        $type = $this->payload['vm_type'] ?? 'qemu';
        return $vmid !== null ? $type.':'.$vmid : null;
    }

    protected function client(): ProxmoxClient
    {
        return app(ProxmoxClient::class);
    }

    protected function vm(): ?ProxmoxVm
    {
        $vmid = $this->payload['vmid'] ?? null;
        $type = $this->payload['vm_type'] ?? 'qemu';
        if ($vmid === null) {
            return null;
        }
        return ProxmoxVm::where('vmid', $vmid)->where('vm_type', $type)->first();
    }

    protected function node(): string
    {
        return (string) $this->payload['node'];
    }

    protected function vmid(): int
    {
        return (int) $this->payload['vmid'];
    }

    protected function vmType(): string
    {
        return ($this->payload['vm_type'] ?? 'qemu') === 'lxc' ? 'lxc' : 'qemu';
    }

    /**
     * Run an action against the Proxmox API and (optionally) wait for the
     * UPID task to finish. Updates the local VM snapshot status on success.
     */
    protected function dispatchAction(string $action, ?string $expectedStatus = null, array $params = []): array
    {
        $client = $this->client();
        $node = $this->node();

        $upid = $client->vmAction($node, $this->vmid(), $action, $this->vmType(), $params);

        $taskStatus = null;
        if ($upid) {
            $taskStatus = $client->waitForTask($node, $upid, timeoutSeconds: 90, intervalSeconds: 2);

            if ($taskStatus && ($taskStatus['exitstatus'] ?? null) !== 'OK') {
                throw new \RuntimeException(
                    "Proxmox task {$upid} finished with exit status: ".($taskStatus['exitstatus'] ?? 'unknown')
                );
            }
        }

        if ($expectedStatus && ($vm = $this->vm())) {
            $vm->update(['status' => $expectedStatus, 'last_synced_at' => now()]);
        }

        return [
            'action' => $action,
            'upid' => $upid,
            'task' => $taskStatus,
        ];
    }
}
