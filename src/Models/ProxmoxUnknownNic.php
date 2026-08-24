<?php

namespace Nawasara\Proxmox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NIC yang ada tetapi IP-nya belum diketahui — daftar kerja pengisian manual.
 *
 * Selama tabel ini terisi, daftar "IP bebas" belum lengkap. Lihat catatan
 * pada migrasinya.
 */
class ProxmoxUnknownNic extends Model
{
    protected $table = 'nawasara_proxmox_unknown_nics';

    protected $fillable = [
        'vm_id', 'vmid', 'vm_name', 'node_name', 'vm_type', 'vm_status',
        'nic', 'mac', 'bridge', 'suggested_subnet_id', 'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function vm(): BelongsTo
    {
        return $this->belongsTo(ProxmoxVm::class, 'vm_id');
    }

    public function suggestedSubnet(): BelongsTo
    {
        return $this->belongsTo(ProxmoxSubnet::class, 'suggested_subnet_id');
    }
}
