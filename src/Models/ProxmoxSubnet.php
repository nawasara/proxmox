<?php

namespace Nawasara\Proxmox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kolam IP, satu per bridge-subnet.
 *
 * Kapasitasnya dihitung dari prefiks SUNGGUHAN yang dibaca dari Proxmox.
 * Di Ponorogo `vmbr0` adalah /27 — 30 alamat, bukan 254 — dan menebaknya
 * /24 akan menyarankan alamat yang tidak pernah ada.
 */
class ProxmoxSubnet extends Model
{
    protected $table = 'nawasara_proxmox_subnets';

    protected $fillable = [
        'cidr', 'bridge', 'gateway', 'label',
        'is_public', 'reserved_ranges', 'note',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'reserved_ranges' => 'array',
    ];

    public function addresses(): HasMany
    {
        return $this->hasMany(ProxmoxIpAddress::class, 'subnet_id');
    }

    /** Alamat jaringan, mis. `111.1.1.0` dari `111.1.1.0/24`. */
    public function networkAddress(): string
    {
        return explode('/', $this->cidr)[0];
    }

    public function prefixLength(): int
    {
        return (int) (explode('/', $this->cidr)[1] ?? 32);
    }

    /**
     * Jumlah alamat yang DAPAT DIPAKAI host.
     *
     * Alamat jaringan dan broadcast dikurangi — keduanya tidak pernah boleh
     * diberikan ke VM. Prefiks /31 dan /32 dikecualikan: keduanya tidak punya
     * broadcast, dan mengurangi dua akan menghasilkan angka negatif.
     */
    public function usableCount(): int
    {
        $prefix = $this->prefixLength();

        if ($prefix >= 31) {
            return $prefix === 32 ? 1 : 2;
        }

        return (2 ** (32 - $prefix)) - 2;
    }

    public function firstUsableLong(): int
    {
        return $this->networkLong() + 1;
    }

    public function lastUsableLong(): int
    {
        return $this->networkLong() + (2 ** (32 - $this->prefixLength())) - 2;
    }

    public function networkLong(): int
    {
        return (int) ip2long($this->networkAddress());
    }

    /** Apakah sebuah alamat berada di dalam subnet ini? */
    public function contains(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        $mask = -1 << (32 - $this->prefixLength());

        return ($long & $mask) === ($this->networkLong() & $mask);
    }

    /**
     * Apakah alamat ini dicadangkan — gateway, alamat node, perangkat jaringan?
     *
     * Gateway ikut diperiksa meski tidak didaftarkan di `reserved_ranges`:
     * menyarankannya adalah kekeliruan yang pasti memutus jaringan, dan
     * mengandalkan admin mengingat mendaftarkannya terlalu rapuh.
     */
    public function isReserved(string $ip): bool
    {
        if ($this->gateway && $ip === $this->gateway) {
            return true;
        }

        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        foreach ($this->reserved_ranges ?? [] as $range) {
            $from = ip2long($range['from'] ?? '');
            $to = ip2long($range['to'] ?? $range['from'] ?? '');

            if ($from !== false && $to !== false && $long >= $from && $long <= $to) {
                return true;
            }
        }

        return false;
    }
}
