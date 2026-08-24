<?php

$prefix = 'nawasara-proxmox';

return [
    [
        'workspace' => 'proxmox',
        'label' => 'Proxmox',
        'icon' => 'lucide-server',
        'group' => 'Observability',
        'url' => '',
        'permission' => 'proxmox.vm.view',
        'submenu' => [
            [
                'label' => 'Virtual Machines',
                'icon' => 'lucide-monitor',
                'url' => url($prefix.'/vms'),
                'permission' => 'proxmox.vm.view',
                'navigate' => true,
            ],
            [
                'label' => 'Nodes',
                'icon' => 'lucide-cpu',
                'url' => url($prefix.'/nodes'),
                'permission' => 'proxmox.node.view',
                'navigate' => true,
            ],
            [
                'label' => 'Inventori IP',
                'icon' => 'lucide-network',
                'url' => url($prefix.'/ip-inventory'),
                'permission' => 'proxmox.ip.view',
                'navigate' => true,
            ],
        ],
    ],
];
