<?php

namespace Nawasara\Proxmox\Jobs\Vm;

class StartProxmoxVmJob extends AbstractProxmoxVmJob
{
    protected function action(): string
    {
        return 'vm_start';
    }

    protected function execute(): array
    {
        return $this->dispatchAction('start', expectedStatus: 'running');
    }
}
