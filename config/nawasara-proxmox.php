<?php

return [
    'sync_interval' => env('PROXMOX_SYNC_INTERVAL', 15), // minutes

    /*
    |--------------------------------------------------------------------------
    | Selang inventori IP (jam)
    |--------------------------------------------------------------------------
    |
    | Jauh lebih jarang daripada `sync_interval` dengan sengaja: inventori IP
    | memanggil /config sekali per VM — puluhan permintaan tiap putaran —
    | sementara alamat IP nyaris tidak pernah berubah tanpa ada yang
    | mengubahnya.
    |
    */
    'ip_sync_interval_hours' => (int) env('PROXMOX_IP_SYNC_INTERVAL_HOURS', 6),
    'request_timeout' => env('PROXMOX_REQUEST_TIMEOUT', 15), // seconds

    // Scheduler — registers proxmox:sync (nodes + VMs) on the Laravel
    // schedule, every `sync_interval` minutes. Set PROXMOX_SCHEDULER_ENABLED
    // false to skip registration, e.g. when the deployment has no Proxmox
    // API credentials yet (the scheduled task would just fail every run).
    'scheduler' => [
        'enabled' => env('PROXMOX_SCHEDULER_ENABLED', true),
    ],
];
