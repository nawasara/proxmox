<?php

namespace Nawasara\Proxmox\Jobs\Vm;

class DeleteSnapshotJob extends AbstractProxmoxVmJob
{
    public int $timeout = 300;

    protected function action(): string
    {
        return 'snapshot_delete';
    }

    protected function execute(): array
    {
        $snapName = (string) ($this->payload['snap_name'] ?? '');
        if ($snapName === '') {
            throw new \InvalidArgumentException('snap_name payload is required');
        }

        $upid = $this->client()->deleteSnapshot(
            $this->node(), $this->vmid(), $snapName, $this->vmType()
        );

        $task = $upid ? $this->waitForTaskOrFail($upid, timeoutSeconds: 300) : null;

        return [
            'action' => 'snapshot_delete',
            'snap_name' => $snapName,
            'upid' => $upid,
            'task' => $task,
            'node' => $this->node(),
        ];
    }
}
