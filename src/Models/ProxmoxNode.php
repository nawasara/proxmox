<?php

namespace Nawasara\Proxmox\Models;

use Illuminate\Database\Eloquent\Model;
use Nawasara\Sync\Concerns\HasSyncStatus;

class ProxmoxNode extends Model
{
    use HasSyncStatus;

    protected $table = 'nawasara_proxmox_nodes';

    protected $fillable = [
        'node_name', 'status', 'type',
        'cpu_count', 'cpu_usage',
        'mem_total', 'mem_used',
        'disk_total', 'disk_used',
        'uptime',
        'pve_version', 'kernel_version',
        'sync_status', 'sync_error', 'last_synced_at', 'content_hash',
    ];

    protected $casts = [
        'cpu_count' => 'integer',
        'cpu_usage' => 'float',
        'mem_total' => 'integer',
        'mem_used' => 'integer',
        'disk_total' => 'integer',
        'disk_used' => 'integer',
        'uptime' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }
        return $query->where('node_name', 'like', '%'.$term.'%');
    }

    public function vms()
    {
        return $this->hasMany(ProxmoxVm::class, 'node_name', 'node_name');
    }

    public function memUsagePercent(): ?float
    {
        if (! $this->mem_total) {
            return null;
        }
        return round(($this->mem_used / $this->mem_total) * 100, 1);
    }

    public function diskUsagePercent(): ?float
    {
        if (! $this->disk_total) {
            return null;
        }
        return round(($this->disk_used / $this->disk_total) * 100, 1);
    }

    public function computeContentHash(): string
    {
        return hash('sha256', json_encode([
            'status' => $this->status,
            'cpu_count' => $this->cpu_count,
            'mem_total' => $this->mem_total,
            'pve_version' => $this->pve_version,
        ]));
    }
}
