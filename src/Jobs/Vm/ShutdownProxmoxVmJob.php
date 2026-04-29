<?php

namespace Nawasara\Proxmox\Jobs\Vm;

class ShutdownProxmoxVmJob extends AbstractProxmoxVmJob
{
    protected function action(): string
    {
        return 'vm_shutdown';
    }

    protected function execute(): array
    {
        return $this->dispatchAction('shutdown', expectedStatus: 'stopped');
    }
}
