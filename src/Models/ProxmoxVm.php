<?php

namespace Nawasara\Proxmox\Models;

use Illuminate\Database\Eloquent\Model;
use Nawasara\Sync\Concerns\HasSyncStatus;

class ProxmoxVm extends Model
{
    use HasSyncStatus;

    protected $table = 'nawasara_proxmox_vms';

    protected $fillable = [
        'vmid', 'node_name', 'vm_type', 'name',
        'status', 'lock', 'template',
        'cpu_count', 'cpu_usage',
        'mem_total', 'mem_used',
        'disk_total', 'disk_used',
        'uptime',
        'ip_addresses', 'tags', 'description',
        'sync_status', 'sync_error', 'last_synced_at', 'content_hash',
    ];

    protected $casts = [
        'vmid' => 'integer',
        'template' => 'boolean',
        'cpu_count' => 'integer',
        'cpu_usage' => 'float',
        'mem_total' => 'integer',
        'mem_used' => 'integer',
        'disk_total' => 'integer',
        'disk_used' => 'integer',
        'uptime' => 'integer',
        'ip_addresses' => 'array',
        'tags' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function node()
    {
        return $this->belongsTo(ProxmoxNode::class, 'node_name', 'node_name');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeStopped($query)
    {
        return $query->where('status', 'stopped');
    }

    public function scopeOfType($query, ?string $type)
    {
        if (! $type) {
            return $query;
        }
        return $query->where('vm_type', $type);
    }

    public function scopeOnNode($query, ?string $node)
    {
        if (! $node) {
            return $query;
        }
        return $query->where('node_name', $node);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }
        $term = '%'.$term.'%';
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', $term)
                ->orWhere('vmid', 'like', $term)
                ->orWhere('description', 'like', $term);
        });
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

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isLocked(): bool
    {
        return ! empty($this->lock);
    }

    public function computeContentHash(): string
    {
        return hash('sha256', json_encode([
            'status' => $this->status,
            'name' => $this->name,
            'cpu_count' => $this->cpu_count,
            'mem_total' => $this->mem_total,
            'disk_total' => $this->disk_total,
            'tags' => $this->tags,
            'template' => $this->template,
        ]));
    }
}
