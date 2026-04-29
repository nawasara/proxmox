<?php

$prefix = 'nawasara-proxmox';

return [
    [
        'workspace' => 'proxmox',
        'label' => 'Proxmox VE',
        'icon' => 'lucide-server',
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
        ],
    ],
];
