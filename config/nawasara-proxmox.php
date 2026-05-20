<?php

return [
    'sync_interval' => env('PROXMOX_SYNC_INTERVAL', 15), // minutes
    'request_timeout' => env('PROXMOX_REQUEST_TIMEOUT', 15), // seconds

    // Scheduler — registers proxmox:sync (nodes + VMs) on the Laravel
    // schedule, every `sync_interval` minutes. Set PROXMOX_SCHEDULER_ENABLED
    // false to skip registration, e.g. when the deployment has no Proxmox
    // API credentials yet (the scheduled task would just fail every run).
    'scheduler' => [
        'enabled' => env('PROXMOX_SCHEDULER_ENABLED', true),
    ],
];
