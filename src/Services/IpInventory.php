<?php

namespace Nawasara\Proxmox\Services;

use Nawasara\Proxmox\Models\ProxmoxIpAddress;
use Nawasara\Proxmox\Models\ProxmoxSubnet;
use Nawasara\Proxmox\Models\ProxmoxUnknownNic;

/**
 * Menjawab "alamat mana yang boleh saya pakai untuk VM baru?"
 *
 * ⚠️ **Jawabannya berderajat, bukan ya/tidak.** Separuh NIC di Ponorogo tidak
 * punya IP yang dapat dibaca Proxmox, jadi sebuah alamat yang tampak bebas
 * dapat saja sedang dipakai VM yang tidak terlacak. Kelas ini karena itu
 * SELALU mengembalikan jumlah NIC tak dikenal bersama daftar bebasnya —
 * memisahkan keduanya akan membuat daftar itu terbaca lebih pasti daripada
 * yang sebenarnya, dan bentrok IP publik memutus layanan.
 */
class IpInventory
{
    /**
     * Ringkasan pemakaian satu subnet.
     *
     * @return array{
     *   subnet: ProxmoxSubnet, usable: int, used: int, reserved: int,
     *   free: int, unknown_nics: int, confidence: string
     * }
     */
    public function summarise(ProxmoxSubnet $subnet): array
    {
        $usable = $subnet->usableCount();

        $used = ProxmoxIpAddress::where('subnet_id', $subnet->id)
            ->distinct('ip')
            ->count('ip');

        $reserved = $this->reservedCount($subnet);

        // NIC tak dikenal yang bridge-nya menunjuk subnet ini. Bukan bukti
        // ia memakai alamat di sini, tetapi cukup untuk memperingatkan.
        $unknown = ProxmoxUnknownNic::where('bridge', $subnet->bridge)->count();

        $free = max(0, $usable - $used - $reserved);

        return [
            'subnet' => $subnet,
            'usable' => $usable,
            'used' => $used,
            'reserved' => $reserved,
            'free' => $free,
            'unknown_nics' => $unknown,
            'confidence' => $unknown === 0 ? 'pasti' : 'perkiraan',
        ];
    }

    /**
     * Alamat yang tampak bebas di sebuah subnet, terurut.
     *
     * @param  int  $limit  Dibatasi supaya /16 tidak menghasilkan 65 ribu baris.
     * @return array<int,string>
     */
    public function freeAddresses(ProxmoxSubnet $subnet, int $limit = 50): array
    {
        // Seluruh alamat terpakai di subnet ini, sekali ambil. Memeriksa satu
        // per satu ke basis data akan menghasilkan ratusan kueri.
        $taken = ProxmoxIpAddress::where('subnet_id', $subnet->id)
            ->pluck('ip')
            ->flip();

        $free = [];
        $first = $subnet->firstUsableLong();
        $last = $subnet->lastUsableLong();

        // Batas keras: subnet luas tidak boleh menggantungkan permintaan.
        // 4096 cukup untuk /20 ke bawah, dan lebih luas dari itu tidak pernah
        // ditelusuri satu per satu oleh manusia.
        $scanned = 0;

        for ($long = $first; $long <= $last && count($free) < $limit; $long++) {
            if (++$scanned > 4096) {
                break;
            }

            $ip = long2ip($long);

            if (isset($taken[$ip]) || $subnet->isReserved($ip)) {
                continue;
            }

            $free[] = $ip;
        }

        return $free;
    }

    /**
     * Alamat yang dipakai LEBIH DARI SATU NIC — bentrok yang sudah terjadi.
     *
     * Dicari terpisah, bukan sekadar ditampilkan di daftar: bentrok IP jarang
     * disadari sampai salah satu layanan mati, dan inventori yang tidak
     * menunjukkannya kehilangan kegunaan terbesarnya.
     *
     * @return array<int,array{ip:string,pemakai:\Illuminate\Support\Collection}>
     */
    public function conflicts(): array
    {
        $duplicated = ProxmoxIpAddress::selectRaw('ip, COUNT(*) as jumlah')
            ->groupBy('ip')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('ip');

        if ($duplicated->isEmpty()) {
            return [];
        }

        return ProxmoxIpAddress::whereIn('ip', $duplicated)
            ->ordered()
            ->get()
            ->groupBy('ip')
            ->map(fn ($rows, $ip) => ['ip' => $ip, 'pemakai' => $rows])
            ->values()
            ->all();
    }

    /** Berapa alamat yang tertutup `reserved_ranges` dan gateway. */
    protected function reservedCount(ProxmoxSubnet $subnet): int
    {
        $count = 0;
        $first = $subnet->firstUsableLong();
        $last = $subnet->lastUsableLong();

        foreach ($subnet->reserved_ranges ?? [] as $range) {
            $from = ip2long($range['from'] ?? '');
            $to = ip2long($range['to'] ?? $range['from'] ?? '');

            if ($from === false || $to === false) {
                continue;
            }

            // Dipotong ke batas subnet — rentang yang ditulis melebar tidak
            // boleh membuat jumlah tercadang melampaui kapasitasnya.
            $from = max($from, $first);
            $to = min($to, $last);

            if ($to >= $from) {
                $count += $to - $from + 1;
            }
        }

        // Gateway dihitung bila belum tercakup rentang mana pun.
        if ($subnet->gateway && ! $this->inRanges($subnet, $subnet->gateway)) {
            $count++;
        }

        return $count;
    }

    protected function inRanges(ProxmoxSubnet $subnet, string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        foreach ($subnet->reserved_ranges ?? [] as $range) {
            $from = ip2long($range['from'] ?? '');
            $to = ip2long($range['to'] ?? $range['from'] ?? '');

            if ($from !== false && $to !== false && $long >= $from && $long <= $to) {
                return true;
            }
        }

        return false;
    }
}
