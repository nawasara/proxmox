<?php

namespace Nawasara\Proxmox\Jobs\Vm;

use Nawasara\Proxmox\Jobs\SyncProxmoxVmsJob;

class RollbackSnapshotJob extends AbstractProxmoxVmJob
{
    public int $timeout = 600;

    protected function action(): string
    {
        return 'snapshot_rollback';
    }

    protected function execute(): array
    {
        $snapName = (string) ($this->payload['snap_name'] ?? '');
        if ($snapName === '') {
            throw new \InvalidArgumentException('snap_name payload is required');
        }

        $upid = $this->client()->rollbackSnapshot(
            $this->node(), $this->vmid(), $snapName, $this->vmType()
        );

        $task = $upid ? $this->waitForTaskOrFail($upid, timeoutSeconds: 600) : null;

        // Rollback can change the VM's running state (depends on the snapshot)
        // — re-sync to capture whatever Proxmox decided.
        SyncProxmoxVmsJob::dispatch(triggerSource: 'post_action');

        return [
            'action' => 'snapshot_rollback',
            'snap_name' => $snapName,
            'upid' => $upid,
            'task' => $task,
            'node' => $this->node(),
        ];
    }
}
