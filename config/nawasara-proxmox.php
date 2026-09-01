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

    /*
    |--------------------------------------------------------------------------
    | Awalan alamat yang sebenarnya INTERNAL
    |--------------------------------------------------------------------------
    |
    | Rentang yang dipakai sebagai jaringan dalam meski bukan RFC1918.
    | `111.1.1.x` di Ponorogo adalah contohnya — tanpa daftar ini ia ditandai
    | "publik", dan kartunya menuntut perhatian yang tidak perlu sementara
    | subnet publik yang benar-benar sempit tenggelam di bawahnya.
    |
    */
    'internal_prefixes' => array_filter(explode(',', (string) env(
        'PROXMOX_INTERNAL_PREFIXES',
        '111.1.1.,14.64.16.',
    ))),
    /*
    |--------------------------------------------------------------------------
    | Ambang peringatan kapasitas
    |--------------------------------------------------------------------------
    |
    | Ditulis setelah basis data mati karena disknya penuh — tanpa satu pun
    | peringatan sebelumnya. Nilai-nilai di bawah dipilih supaya masih ADA
    | WAKTU untuk bertindak, bukan sekadar memberi tahu setelah terlambat.
    |
    | 80% memberi ruang beberapa hari pada mesin yang tumbuh wajar; 90% berarti
    | tindakan hari itu juga. Disk yang penuh tidak memperlambat basis data —
    | ia menghentikannya, dan pemulihannya jauh lebih mahal daripada
    | membersihkan berkas lebih awal.
    |
    | Memori dibiarkan lebih longgar (90/95): Linux memang memakai memori bebas
    | sebagai cache, jadi angka tinggi belum tentu masalah. Disk tidak begitu.
    |
    */
    'thresholds' => [
        'disk_warning' => (int) env('PROXMOX_DISK_WARNING', 80),
        'disk_critical' => (int) env('PROXMOX_DISK_CRITICAL', 90),
        'mem_warning' => (int) env('PROXMOX_MEM_WARNING', 90),
        'mem_critical' => (int) env('PROXMOX_MEM_CRITICAL', 95),

        // Sisa ruang minimum (GB) sebelum persentase dianggap berarti.
        //
        // Persentase saja menyesatkan pada disk besar: 84% dari 1 TB masih
        // menyisakan 150 GB — tidak ada yang perlu dikerjakan malam ini,
        // tetapi peringatannya berbunyi sama nyaringnya dengan 84% dari 20 GB
        // yang menyisakan 3 GB. Peringatan yang berbunyi saat tidak ada yang
        // perlu dilakukan adalah cara tercepat membuat orang berhenti
        // membacanya.
        //
        // Jadi keduanya harus terpenuhi: persentasenya tinggi DAN sisanya
        // benar-benar tipis.
        'disk_min_free_gb' => (int) env('PROXMOX_DISK_MIN_FREE_GB', 20),
    ],

    'request_timeout' => env('PROXMOX_REQUEST_TIMEOUT', 15), // seconds

    // Scheduler — registers proxmox:sync (nodes + VMs) on the Laravel
    // schedule, every `sync_interval` minutes. Set PROXMOX_SCHEDULER_ENABLED
    // false to skip registration, e.g. when the deployment has no Proxmox
    // API credentials yet (the scheduled task would just fail every run).
    'scheduler' => [
        'enabled' => env('PROXMOX_SCHEDULER_ENABLED', true),
    ],
];
