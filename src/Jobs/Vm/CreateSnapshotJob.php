<?php

namespace Nawasara\Proxmox\Jobs\Vm;

class CreateSnapshotJob extends AbstractProxmoxVmJob
{
    public int $timeout = 600;

    protected function action(): string
    {
        return 'snapshot_create';
    }

    protected function execute(): array
    {
        $snapName = (string) ($this->payload['snap_name'] ?? '');
        $description = (string) ($this->payload['description'] ?? '');
        $includeRam = (bool) ($this->payload['vmstate'] ?? false);

        if ($snapName === '') {
            throw new \InvalidArgumentException('snap_name payload is required');
        }

        $params = ['description' => $description];
        if ($includeRam && $this->vmType() === 'qemu') {
            $params['vmstate'] = 1;
        }

        $upid = $this->client()->createSnapshot(
            $this->node(), $this->vmid(), $snapName, $this->vmType(), $params
        );

        $task = $upid ? $this->waitForTaskOrFail($upid, timeoutSeconds: 600) : null;

        return [
            'action' => 'snapshot_create',
            'snap_name' => $snapName,
            'upid' => $upid,
            'task' => $task,
            'node' => $this->node(),
        ];
    }
}
