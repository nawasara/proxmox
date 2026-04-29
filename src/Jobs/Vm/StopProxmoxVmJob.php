<?php

namespace Nawasara\Proxmox\Jobs\Vm;

class StopProxmoxVmJob extends AbstractProxmoxVmJob
{
    protected function action(): string
    {
        return 'vm_stop';
    }

    protected function execute(): array
    {
        return $this->dispatchAction('stop', expectedStatus: 'stopped');
    }
}
