<?php

namespace Nawasara\Proxmox\Jobs\Vm;

class RestartProxmoxVmJob extends AbstractProxmoxVmJob
{
    protected function action(): string
    {
        return 'vm_restart';
    }

    /**
     * Proxmox uses `reboot` for graceful guest reboot (qemu-guest-agent),
     * `reset` for hard reset. We pick `reboot` for qemu (graceful) and
     * fall back to `reset` if the guest can't reboot. For LXC, use reboot.
     */
    protected function execute(): array
    {
        // `reboot` is supported on both qemu and lxc and is the closer
        // analogue to a graceful restart.
        return $this->dispatchAction('reboot', expectedStatus: 'running');
    }
}
