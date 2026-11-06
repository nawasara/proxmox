<?php

namespace Nawasara\Proxmox\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Nawasara\Proxmox\Models\ConsoleSession;
use Nawasara\Proxmox\Models\ProxmoxVm;
use RuntimeException;

/**
 * Console container untuk PIC lewat Akun Kisara.
 *
 * Berbeda dari console panel dalam tiga hal, dan ketiganya disengaja:
 *
 * 1. Akun Proxmox-nya `kisara-console@pve` (hanya VM.Console di /vms), bukan
 *    root@pam. Tiket yang bocor dari internet membuka satu console, bukan
 *    seluruh cluster.
 * 2. Langsung ke console CONTAINER (`/lxc/{vmid}/termproxy`), tidak lewat
 *    shell node + `pct enter`. Container disetel `cmode=shell`, jadi console
 *    langsung membuka shell root di dalam container tanpa layar login.
 *    Container yang `cmode`-nya belum berlaku (belum di-restart sejak disetel)
 *    akan menampilkan `login:` — bukan galat Nawasara.
 * 3. Halamannya dibuka TANPA sesi panel. Pengamannya: siapa yang boleh sudah
 *    diperiksa pemanggil (PIC di registry + login ulang di Akun Kisara),
 *    tautannya sekali pakai dan berumur `ticket_ttl` detik, dan bila IP
 *    peminta diketahui, tautan hanya dapat dibuka dari IP yang sama.
 *
 * Hanya LXC: QEMU tidak punya `cmode`, dan console-nya menuntut login.
 */
class KisaraConsole
{
    public const CHANNEL = 'kisara';

    public const CACHE_PREFIX = 'proxmox:kisara-console:';

    public function __construct(protected ProxmoxClient $client) {}

    /** Host console terisi dan akun kisara-console sudah ada di Vault. */
    public function enabled(): bool
    {
        return self::host() !== '' && $this->client->hasKisaraConsoleCredentials();
    }

    public static function host(): string
    {
        return trim((string) config('nawasara-proxmox.kisara_console.host', ''));
    }

    /** Apakah mesin ini dapat dibuka console-nya dari Akun Kisara. */
    public function supports(?ProxmoxVm $vm): bool
    {
        return $vm !== null && $vm->vm_type === 'lxc' && ! $vm->template && $vm->isRunning();
    }

    /**
     * Terbitkan tautan console sekali pakai.
     *
     * Pemanggil WAJIB sudah memastikan $user adalah PIC mesin ini. Layanan ini
     * tidak membaca registry supaya tetap satu urusan; yang memutuskan siapa
     * boleh adalah lapisan yang tahu siapa yang meminta.
     *
     * @throws RuntimeException pesan yang boleh ditampilkan ke pemakai
     */
    public function issue(Authenticatable $user, ProxmoxVm $vm, ?string $clientIp = null): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Console belum disiapkan. Hubungi tim infrastruktur Diskominfo.');
        }

        if ($vm->vm_type !== 'lxc') {
            throw new RuntimeException('Console hanya tersedia untuk container.');
        }

        if (! $vm->isRunning()) {
            throw new RuntimeException('Server ini sedang mati. Hubungi tim infrastruktur Diskominfo untuk menyalakannya.');
        }

        $ticket = $this->client->createTermTicket($vm->node_name, (int) $vm->vmid, 'lxc', kisara: true);

        if (! $ticket || empty($ticket['ticket']) || empty($ticket['port'])) {
            throw new RuntimeException('Proxmox menolak membuka console. Coba lagi beberapa saat lagi.');
        }

        $wsUrl = $this->client->termWebSocketUrl(
            $vm->node_name,
            (int) $vm->vmid,
            'lxc',
            (int) $ticket['port'],
            (string) $ticket['ticket'],
            (string) ($ticket['session_ticket'] ?? ''),
            self::host(),
        );

        // Dicatat sebelum tautan diberikan: console yang terbuka tanpa jejak
        // tidak dapat dipertanggungjawabkan.
        $log = ConsoleSession::create([
            'user_id' => $user->getAuthIdentifier(),
            'user_name' => $user->name ?? null,
            'user_email' => $user->email ?? null,
            'channel' => self::CHANNEL,
            'node_name' => $vm->node_name,
            'vmid' => (int) $vm->vmid,
            'vm_name' => $vm->name,
            'vm_type' => $vm->vm_type,
            'started_at' => now(),
            'last_seen_at' => now(),
            'ip_address' => $clientIp,
        ]);

        $key = bin2hex(random_bytes(16));

        Cache::put(self::CACHE_PREFIX.$key, [
            'user_id' => $user->getAuthIdentifier(),
            'client_ip' => $clientIp,
            // Halaman console dibuka tanpa sesi panel, jadi nama di bilah
            // atasnya dibawa dari sini, bukan dari auth()->user().
            'user_name' => $user->name ?? null,
            'ws_url' => $wsUrl,
            'term_ticket' => (string) $ticket['ticket'],
            'console_user' => (string) ($ticket['console_user'] ?? ''),
            'session_id' => $log->id,
            'vm_name' => $vm->name,
            'node' => $vm->node_name,
            'vmid' => (int) $vm->vmid,
            'type' => $vm->vm_type,
        ], max(10, (int) config('nawasara-proxmox.kisara_console.ticket_ttl', 60)));

        return 'https://'.self::host().'/c/'.$key;
    }
}
