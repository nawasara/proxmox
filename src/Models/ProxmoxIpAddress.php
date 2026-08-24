<?php

namespace Nawasara\Proxmox\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu alamat IP yang diketahui terpakai.
 *
 * `source` membedakan dua asal yang tidak setara — `config` dibaca dari
 * Proxmox, `manual` diketik orang karena Proxmox tidak mengetahuinya. Lihat
 * catatan pada migrasinya.
 */
class ProxmoxIpAddress extends Model
{
    protected $table = 'nawasara_proxmox_ip_addresses';

    public const SOURCE_CONFIG = 'config';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'ip', 'ip_numeric', 'subnet_id', 'vm_id', 'vmid', 'vm_name',
        'node_name', 'nic', 'mac', 'bridge', 'source', 'filled_by',
        'note', 'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * `ip_numeric` diisi otomatis dari `ip`.
     *
     * Ditaruh di model, bukan diserahkan kepada pemanggil: sekali ada satu
     * jalur penulisan yang lupa mengisinya, baris itu tenggelam ke urutan
     * paling atas dan orang mengira datanya hilang.
     */
    protected static function booted(): void
    {
        static::saving(function (self $row) {
            $long = ip2long((string) $row->ip);
            $row->ip_numeric = $long === false ? null : $long;
        });
    }

    public function subnet(): BelongsTo
    {
        return $this->belongsTo(ProxmoxSubnet::class, 'subnet_id');
    }

    public function vm(): BelongsTo
    {
        return $this->belongsTo(ProxmoxVm::class, 'vm_id');
    }

    public function isManual(): bool
    {
        return $this->source === self::SOURCE_MANUAL;
    }

    public function scopeManual(Builder $q): Builder
    {
        return $q->where('source', self::SOURCE_MANUAL);
    }

    public function scopeFromConfig(Builder $q): Builder
    {
        return $q->where('source', self::SOURCE_CONFIG);
    }

    /** Urut sebagai angka — "111.1.1.9" sebelum "111.1.1.10". */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('ip_numeric');
    }
}
