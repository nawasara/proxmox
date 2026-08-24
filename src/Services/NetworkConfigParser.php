<?php

namespace Nawasara\Proxmox\Services;

/**
 * Mengurai baris jaringan pada config VM Proxmox.
 *
 * Bentuknya berbeda antara LXC dan QEMU, dan perbedaan itulah yang menentukan
 * separuh armada tidak terlacak:
 *
 *   LXC   net0 = name=eth0,bridge=vmbr1,hwaddr=BC:24:11:B6:9C:3E,ip=111.1.1.53/24,type=veth
 *   QEMU  net0 = virtio=46:96:DB:E3:84:08,bridge=vmbr0,firewall=1
 *                        ↑ MAC, TANPA ip=
 *
 * QEMU menyimpan alamatnya di dalam sistem operasi tamu, bukan di Proxmox —
 * jadi tidak ada yang dapat diurai. MAC-nya tetap diambil, karena itulah
 * penuntun yang dipakai orang saat mengisi IP-nya secara manual.
 */
class NetworkConfigParser
{
    /**
     * @param  array<string,mixed>  $config  Isi `data` dari /config.
     * @return array<int,array{nic:string,mac:?string,bridge:?string,ips:array<int,array{ip:string,prefix:int}>,gateway:?string}>
     */
    public function parse(array $config): array
    {
        $nics = [];

        foreach ($config as $key => $value) {
            if (! preg_match('/^net(\d+)$/', (string) $key)) {
                continue;
            }

            $nics[] = [
                'nic' => (string) $key,
                'mac' => $this->extractMac((string) $value),
                'bridge' => $this->extractPair((string) $value, 'bridge'),
                'ips' => $this->extractIps((string) $value, $config, (string) $key),
                'gateway' => $this->extractPair((string) $value, 'gw'),
            ];
        }

        // net0, net1, net2 … — bukan urutan acak dari PHP.
        usort($nics, fn ($a, $b) => strnatcmp($a['nic'], $b['nic']));

        return $nics;
    }

    /**
     * MAC dari kedua bentuk.
     *
     * LXC memakai `hwaddr=`; QEMU menaruhnya sebagai NILAI dari nama model
     * kartunya (`virtio=`, `e1000=`, `vmxnet3=` …). Karena nama modelnya
     * bermacam-macam, MAC dicari sebagai POLA, bukan lewat daftar nama model
     * — daftar seperti itu pasti tertinggal saat Proxmox menambah model baru.
     */
    protected function extractMac(string $value): ?string
    {
        if (preg_match('/hwaddr=([0-9A-Fa-f:]{17})/', $value, $m)) {
            return strtoupper($m[1]);
        }

        if (preg_match('/(?:^|,)[a-z0-9]+=([0-9A-Fa-f]{2}(?::[0-9A-Fa-f]{2}){5})(?:,|$)/', $value, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /**
     * Alamat IP milik satu NIC.
     *
     * Dua tempat diperiksa:
     *   1. `ip=` di dalam baris net itu sendiri — cara LXC.
     *   2. `ipconfigN` terpisah — cara QEMU ber-cloud-init. Jarang dipakai di
     *      sini, tetapi bila ada ia satu-satunya alamat QEMU yang terbaca,
     *      jadi tidak boleh dilewatkan.
     *
     * @param  array<string,mixed>  $config
     * @return array<int,array{ip:string,prefix:int}>
     */
    protected function extractIps(string $value, array $config, string $nicKey): array
    {
        $found = [];

        if (preg_match_all('/(?:^|,)ip=([0-9]{1,3}(?:\.[0-9]{1,3}){3})\/(\d{1,2})/', $value, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $found[] = ['ip' => $hit[1], 'prefix' => (int) $hit[2]];
            }
        }

        // netN → ipconfigN
        $index = str_replace('net', '', $nicKey);
        $ipconfig = (string) ($config['ipconfig'.$index] ?? '');

        if ($ipconfig !== ''
            && preg_match('/ip=([0-9]{1,3}(?:\.[0-9]{1,3}){3})\/(\d{1,2})/', $ipconfig, $m2)) {
            $found[] = ['ip' => $m2[1], 'prefix' => (int) $m2[2]];
        }

        // `ip=dhcp` tidak menghasilkan apa pun di atas — memang benar begitu:
        // alamatnya belum ditentukan, dan menebaknya lebih buruk daripada
        // mengakui tidak tahu.

        return $found;
    }

    protected function extractPair(string $value, string $key): ?string
    {
        if (preg_match('/(?:^|,)'.preg_quote($key, '/').'=([^,]+)/', $value, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
