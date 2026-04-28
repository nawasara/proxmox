<?php

return [
    'sync_interval' => env('PROXMOX_SYNC_INTERVAL', 15), // minutes
    'request_timeout' => env('PROXMOX_REQUEST_TIMEOUT', 15), // seconds
];
