<?php

namespace Nawasara\Proxmox\Jobs;

use Nawasara\Proxmox\Models\ProxmoxIpAddress;
use Nawasara\Proxmox\Models\ProxmoxSubnet;
use Nawasara\Proxmox\Models\ProxmoxUnknownNic;
use Nawasara\Proxmox\Models\ProxmoxVm;
use Nawasara\Proxmox\Services\NetworkConfigParser;
use Nawasara\Proxmox\Services\ProxmoxClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

/**
 * Menginventori alamat IP dari config VM Proxmox.
 *
 * Memanggil `/config` per VM — satu permintaan tiap VM, karena
 * `/cluster/resources` yang dipakai sinkronisasi lain TIDAK mengembalikan
 * data jaringan sama sekali. Karena itu jadwalnya lebih jarang daripada
 * sinkronisasi VM biasa.
 *
 * ⚠️ **Baris `manual` tidak pernah disentuh.** Ia mewakili pengetahuan yang
 * tidak dimiliki Proxmox — alamat VM QEMU, yang tidak dapat dibaca lewat API
 * mana pun dengan token biasa. Menghapusnya berarti membuang satu-satunya
 * catatan yang ada, dan sinkronisasi berapa kali pun tidak dapat
 * memulihkannya.
 */
class SyncProxmoxIpsJob extends AbstractSyncJob
{
    /** Lebih lama: satu permintaan HTTP per VM, dan armadanya puluhan. */
    public int $timeout = 300;

    protected function service(): string
    {
        return 'proxmox';
    }

    protected function action(): string
    {
        return 'sync_ips';
    }

    protected function targetType(): ?string
    {
        return 'ProxmoxIpAddress';
    }

    protected function targetId(): ?string
    {
        return null;
    }

    protected function execute(): array
    {
        $client = app(ProxmoxClient::class);

        if (! $client->isConfigured()) {
            throw new \RuntimeException('Proxmox client is not configured');
        }

        $subnets = $this->syncSubnets($client);
        $parser = new NetworkConfigParser();

        $stats = [
            'subnets' => count($subnets),
            'ip_tercatat' => 0,
            'nic_tanpa_ip' => 0,
            'vm_gagal' => 0,
        ];

        $ipTerlihat = [];
        $nicTerlihat = [];

        foreach (ProxmoxVm::all() as $vm) {
            $config = $client->getVmConfig($vm->node_name, $vm->vmid, $vm->vm_type);

            if ($config === null) {
                // Satu VM yang tidak terbaca tidak boleh menggagalkan seluruh
                // inventori — sisanya tetap berguna.
                $stats['vm_gagal']++;

                continue;
            }

            foreach ($parser->parse($config) as $nic) {
                foreach ($nic['ips'] as $addr) {
                    $subnet = $this->subnetFor($subnets, $addr['ip']);

                    $row = ProxmoxIpAddress::updateOrCreate(
                        ['ip' => $addr['ip'], 'vmid' => $vm->vmid, 'nic' => $nic['nic']],
                        [
                            'subnet_id' => $subnet?->id,
                            'vm_id' => $vm->id,
                            'vm_name' => $vm->name,
                            'node_name' => $vm->node_name,
                            'mac' => $nic['mac'],
                            'bridge' => $nic['bridge'],
                            'source' => ProxmoxIpAddress::SOURCE_CONFIG,
                            'last_seen_at' => now(),
                        ],
                    );

                    $ipTerlihat[] = $row->id;
                    $stats['ip_tercatat']++;
                }

                if ($nic['ips'] !== []) {
                    continue;
                }

                // NIC tanpa IP di config — kecuali orang sudah mengisinya
                // sendiri, dan isian itu lebih tahu daripada Proxmox.
                $sudahDiisi = ProxmoxIpAddress::where('vmid', $vm->vmid)
                    ->where('nic', $nic['nic'])
                    ->manual()
                    ->exists();

                if ($sudahDiisi) {
                    continue;
                }

                $row = ProxmoxUnknownNic::updateOrCreate(
                    ['vmid' => $vm->vmid, 'nic' => $nic['nic']],
                    [
                        'vm_id' => $vm->id,
                        'vm_name' => $vm->name,
                        'node_name' => $vm->node_name,
                        'vm_type' => $vm->vm_type,
                        'vm_status' => $vm->status,
                        'mac' => $nic['mac'],
                        'bridge' => $nic['bridge'],
                        'suggested_subnet_id' => $this->subnetForBridge($subnets, $nic['bridge'])?->id,
                        'last_synced_at' => now(),
                    ],
                );

                $nicTerlihat[] = $row->id;
                $stats['nic_tanpa_ip']++;
            }
        }

        // Bersihkan yang sudah tidak ada lagi di Proxmox — HANYA baris config.
        // Baris manual dilindungi; lihat catatan kelas.
        $stats['ip_dihapus'] = ProxmoxIpAddress::fromConfig()
            ->when($ipTerlihat !== [], fn ($q) => $q->whereNotIn('id', $ipTerlihat))
            ->delete();

        $stats['nic_dibersihkan'] = ProxmoxUnknownNic::query()
            ->when($nicTerlihat !== [], fn ($q) => $q->whereNotIn('id', $nicTerlihat))
            ->delete();

        return $stats;
    }

    /**
     * Membaca bridge tiap node menjadi daftar subnet.
     *
     * Prefiksnya diambil dari Proxmox, tidak ditebak: `vmbr0` di sini adalah
     * prefiks /27 (30 alamat), dan menganggapnya /24 akan menyarankan 224
     * alamat yang tidak pernah ada.
     *
     * @return array<int,ProxmoxSubnet>
     */
    protected function syncSubnets(ProxmoxClient $client): array
    {
        $hasil = [];

        foreach ($client->getNodes() as $node) {
            $name = $node['node'] ?? null;

            if (! $name) {
                continue;
            }

            foreach ($client->getNodeNetwork($name) as $iface) {
                if (($iface['type'] ?? '') !== 'bridge') {
                    continue;
                }

                $cidr = $iface['cidr'] ?? null;

                // Sebagian node melaporkan address + netmask, bukan cidr.
                if (! $cidr && ! empty($iface['address']) && ! empty($iface['netmask'])) {
                    $cidr = $iface['address'].'/'.$this->netmaskToPrefix((string) $iface['netmask']);
                }

                if (! $cidr || ! str_contains((string) $cidr, '/')) {
                    continue;
                }

                $network = $this->networkCidr((string) $cidr);

                if ($network === null) {
                    continue;
                }

                // Satu subnet dipakai bersama SEMUA node — tiap node punya
                // alamatnya sendiri di sana, tetapi kolamnya satu. Dikunci
                // pada `cidr` supaya tidak menjadi empat baris kembar.
                $subnet = ProxmoxSubnet::updateOrCreate(
                    ['cidr' => $network],
                    [
                        'bridge' => $iface['iface'] ?? null,
                        'gateway' => $iface['gateway'] ?? null,
                        'is_public' => $this->isPublic(explode('/', $network)[0]),
                    ],
                );

                $hasil[$subnet->id] = $subnet;
            }
        }

        return $hasil;
    }

    /** `111.1.1.13/24` menjadi `111.1.1.0/24`. */
    protected function networkCidr(string $cidr): ?string
    {
        [$ip, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);

        $long = ip2long((string) $ip);
        $prefix = (int) $prefix;

        if ($long === false || $prefix < 1 || $prefix > 32) {
            return null;
        }

        $mask = -1 << (32 - $prefix);

        return long2ip($long & $mask).'/'.$prefix;
    }

    protected function netmaskToPrefix(string $netmask): int
    {
        $long = ip2long($netmask);

        return $long === false ? 24 : substr_count(decbin($long), '1');
    }

    /** RFC1918 dan sejenisnya dianggap privat; selebihnya publik. */
    protected function isPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /** @param  array<int,ProxmoxSubnet>  $subnets */
    protected function subnetFor(array $subnets, string $ip): ?ProxmoxSubnet
    {
        foreach ($subnets as $subnet) {
            if ($subnet->contains($ip)) {
                return $subnet;
            }
        }

        return null;
    }

    /** @param  array<int,ProxmoxSubnet>  $subnets */
    protected function subnetForBridge(array $subnets, ?string $bridge): ?ProxmoxSubnet
    {
        if (! $bridge) {
            return null;
        }

        foreach ($subnets as $subnet) {
            if ($subnet->bridge === $bridge) {
                return $subnet;
            }
        }

        return null;
    }
}
