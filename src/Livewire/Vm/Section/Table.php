<?php

namespace Nawasara\Proxmox\Livewire\Vm\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Proxmox\Jobs\Vm\AbstractProxmoxVmJob;
use Nawasara\Proxmox\Models\ProxmoxNode;
use Nawasara\Proxmox\Models\ProxmoxVm;
use Nawasara\Proxmox\Repositories\ProxmoxVmRepository;
use Nawasara\Proxmox\Services\ProxmoxClient;
use Nawasara\Sync\Models\SyncJob;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;

class Table extends Component
{
    use HasBrowserToast;
    use WithPagination;

    #[Url(except: '')]
    public string $nodeFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    public string $templateFilter = 'hide'; // default sembunyikan template

    public string $search = '';
    public int $perPage = 50;

    // Detail modal
    public ?int $detailId = null;

    // Log modal
    public ?int $logSyncJobId = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedNodeFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }
    public function updatedTemplateFilter(): void { $this->resetPage(); }

    protected function repo(): ProxmoxVmRepository
    {
        return new ProxmoxVmRepository();
    }

    #[Computed]
    public function vms()
    {
        return $this->repo()->list([
            'search' => $this->search ?: null,
            'node' => $this->nodeFilter ?: null,
            'status' => $this->statusFilter ?: null,
            'type' => $this->typeFilter ?: null,
            'template' => $this->templateFilter ?: null,
        ], $this->perPage);
    }

    #[Computed]
    public function nodeOptions(): array
    {
        $nodes = ProxmoxNode::orderBy('node_name')->pluck('node_name')->all();
        $opts = ['all' => 'Semua Node'];
        foreach ($nodes as $n) {
            $opts[$n] = $n;
        }
        return $opts;
    }

    #[Computed]
    public function statusCounts(): array
    {
        return ProxmoxVm::query()
            ->where('template', false)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();
    }

    #[Computed]
    public function lastSyncedAt(): ?string
    {
        $when = $this->repo()->lastSyncedAt();
        return $when ? $when->diffForHumans() : null;
    }

    public function refresh(): void
    {
        Gate::authorize('proxmox.sync.execute');
        $this->repo()->syncNow();
        $this->toastSuccess('Sync dispatched. Data akan refresh dalam beberapa detik.');
    }

    /**
     * Dispatch a lifecycle action against a VM.
     *
     * @param  string  $action  one of: start, stop, shutdown, restart
     */
    public function vmAction(int $id, string $action): void
    {
        Gate::authorize('proxmox.vm.lifecycle');

        $vm = ProxmoxVm::find($id);
        if (! $vm) {
            $this->toastError('VM tidak ditemukan.');
            return;
        }

        $repo = $this->repo();

        try {
            $job = match ($action) {
                'start' => $repo->start($vm),
                'stop' => $repo->stop($vm),
                'shutdown' => $repo->shutdown($vm),
                'restart' => $repo->restart($vm),
                default => throw new \InvalidArgumentException("Unsupported action: {$action}"),
            };
        } catch (\Throwable $e) {
            $this->toastError('Gagal dispatch action: '.$e->getMessage());
            return;
        }

        $this->toastSuccess(ucfirst($action)." VM #{$vm->vmid} ({$vm->name}) telah dijadwalkan.");
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->dispatch('modal-open:proxmox-vm-detail');
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
        $this->dispatch('modal-close:proxmox-vm-detail');
    }

    #[Computed]
    public function detail(): ?ProxmoxVm
    {
        return $this->detailId ? ProxmoxVm::find($this->detailId) : null;
    }

    /**
     * Live (instant) status for the open detail modal. This is a freshly
     * fetched snapshot from Proxmox, NOT the cached DB row — it changes
     * every render while the detail modal is open and a poll is firing.
     */
    #[Computed]
    public function detailLive(): ?array
    {
        $vm = $this->detail;
        if (! $vm || ! $vm->isRunning()) {
            return null;
        }

        try {
            $status = app(ProxmoxClient::class)->getVmStatus(
                $vm->node_name, (int) $vm->vmid, $vm->vm_type
            );
        } catch (\Throwable) {
            return null;
        }

        if (! $status) {
            return null;
        }

        $maxMem = (float) ($status['maxmem'] ?? 0);
        $usedMem = (float) ($status['mem'] ?? 0);

        return [
            'cpu_pct' => round(((float) ($status['cpu'] ?? 0)) * 100, 2),
            'mem_used' => (int) $usedMem,
            'mem_total' => (int) $maxMem,
            'mem_pct' => $maxMem > 0 ? round(($usedMem / $maxMem) * 100, 2) : null,
            'uptime' => (int) ($status['uptime'] ?? 0),
            'fetched_at' => now()->format('H:i:s'),
        ];
    }

    /**
     * RRD time-series for the open detail modal — last hour, AVERAGE.
     * Returns ['cpu' => [floats], 'mem_pct' => [floats]] for sparklines.
     * Each series has the same length and is keyed in chronological order.
     */
    #[Computed]
    public function detailRrd(): ?array
    {
        $vm = $this->detail;
        if (! $vm || ! $vm->isRunning()) {
            return null;
        }

        try {
            $rows = app(ProxmoxClient::class)->getVmRrdData(
                $vm->node_name, (int) $vm->vmid, $vm->vm_type, 'hour', 'AVERAGE'
            );
        } catch (\Throwable) {
            return null;
        }

        if (empty($rows)) {
            return null;
        }

        $cpu = [];
        $memPct = [];
        $maxCpuPct = 0.0;
        $maxMemPct = 0.0;
        $sumCpu = 0.0;
        $sumMem = 0.0;

        foreach ($rows as $row) {
            // Proxmox RRD reports cpu as 0..1 fraction
            $c = isset($row['cpu']) ? round(((float) $row['cpu']) * 100, 2) : null;
            $maxMem = (float) ($row['maxmem'] ?? 0);
            $usedMem = (float) ($row['mem'] ?? 0);
            $m = $maxMem > 0 ? round(($usedMem / $maxMem) * 100, 2) : null;

            if ($c !== null) {
                $cpu[] = $c;
                $maxCpuPct = max($maxCpuPct, $c);
                $sumCpu += $c;
            }
            if ($m !== null) {
                $memPct[] = $m;
                $maxMemPct = max($maxMemPct, $m);
                $sumMem += $m;
            }
        }

        if (empty($cpu) && empty($memPct)) {
            return null;
        }

        return [
            'cpu' => $cpu,
            'mem_pct' => $memPct,
            'cpu_max_pct' => $maxCpuPct,
            'mem_max_pct' => $maxMemPct,
            'cpu_avg_pct' => count($cpu) > 0 ? round($sumCpu / count($cpu), 2) : 0,
            'mem_avg_pct' => count($memPct) > 0 ? round($sumMem / count($memPct), 2) : 0,
            'sample_count' => count($rows),
        ];
    }

    /**
     * Live config from Proxmox API for the currently open detail modal.
     * Returns ['raw' => array, 'networks' => array, 'disks' => array] —
     * extracted so the view doesn't need to grok Proxmox naming conventions.
     */
    #[Computed]
    public function detailConfig(): ?array
    {
        $vm = $this->detail;
        if (! $vm) {
            return null;
        }

        try {
            $raw = app(ProxmoxClient::class)->getVmConfig($vm->node_name, (int) $vm->vmid, $vm->vm_type);
        } catch (\Throwable) {
            return null;
        }

        if (! $raw) {
            return null;
        }

        $networks = [];
        $disks = [];

        foreach ($raw as $key => $value) {
            // Network interfaces: net0, net1, ... (qemu) and net0 (lxc)
            if (preg_match('/^net(\d+)$/', $key, $m)) {
                $parts = [];
                foreach (explode(',', (string) $value) as $segment) {
                    if (str_contains($segment, '=')) {
                        [$k, $v] = explode('=', $segment, 2);
                        $parts[$k] = $v;
                    }
                }
                $networks[] = ['id' => $key, 'parts' => $parts, 'raw' => $value];
            }

            // Disks: scsi0, ide0, virtio0, sata0 (qemu) or rootfs/mp0 (lxc)
            if (preg_match('/^(scsi|ide|virtio|sata|rootfs|mp)\d*$/', $key)) {
                $disks[] = ['id' => $key, 'raw' => (string) $value];
            }
        }

        return [
            'raw' => $raw,
            'networks' => $networks,
            'disks' => $disks,
            'os_type' => $raw['ostype'] ?? null,
            'boot' => $raw['boot'] ?? null,
            'description' => $raw['description'] ?? null,
        ];
    }

    /**
     * Pre-built console URLs per row, so the dropdown can open them as a
     * normal link (target=_blank). Computed once per render.
     */
    #[Computed]
    public function consoleUrls(): array
    {
        $client = app(ProxmoxClient::class);
        $out = [];
        foreach ($this->vms as $vm) {
            $out[$vm->id] = $client->consoleUrl($vm->node_name, (int) $vm->vmid, $vm->vm_type, $vm->name);
        }
        return $out;
    }

    /**
     * Map of vm.id → latest lifecycle SyncJob row, for in-progress / failed
     * indicators in the table. We compute it once per render so each row
     * gets its tracker without N+1 queries.
     */
    #[Computed]
    public function lifecycleJobs(): array
    {
        $targetIds = $this->vms->getCollection()
            ->map(fn (ProxmoxVm $v) => $v->vm_type.':'.$v->vmid)
            ->all();

        if (empty($targetIds)) {
            return [];
        }

        // Latest job per target_id via subquery
        $latest = SyncJob::query()
            ->where('service', 'proxmox')
            ->where('target_type', 'ProxmoxVm')
            ->whereIn('target_id', $targetIds)
            ->whereIn('action', ['vm_start', 'vm_stop', 'vm_shutdown', 'vm_restart'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('target_id')
            ->map(fn ($group) => $group->first());

        $result = [];
        foreach ($this->vms as $vm) {
            $result[$vm->id] = $latest->get($vm->vm_type.':'.$vm->vmid);
        }
        return $result;
    }

    /**
     * Whether any visible VM has a queued/running lifecycle job. Drives
     * wire:poll so the table auto-refreshes while actions are in flight.
     */
    #[Computed]
    public function hasPendingActions(): bool
    {
        foreach ($this->lifecycleJobs as $job) {
            if ($job && in_array($job->status, [SyncJob::STATUS_QUEUED, SyncJob::STATUS_RUNNING], true)) {
                return true;
            }
        }
        return false;
    }

    public function openLog(int $vmId): void
    {
        $vm = ProxmoxVm::find($vmId);
        if (! $vm) {
            $this->toastError('VM tidak ditemukan.');
            return;
        }

        $job = AbstractProxmoxVmJob::latestActionFor($vm->vm_type, $vm->vmid);
        if (! $job) {
            $this->toastInfo('Belum ada aksi tercatat untuk VM ini.');
            return;
        }

        $this->logSyncJobId = $job->id;
        $this->dispatch('modal-open:proxmox-vm-log');
    }

    public function closeLog(): void
    {
        $this->logSyncJobId = null;
        $this->dispatch('modal-close:proxmox-vm-log');
    }

    #[Computed]
    public function logJob(): ?SyncJob
    {
        return $this->logSyncJobId ? SyncJob::find($this->logSyncJobId) : null;
    }

    /**
     * Pulls the Proxmox task log lines for the currently open log modal.
     * Each entry is { n: int, t: string }. Returns [] if the job has no
     * UPID yet or the API call fails.
     */
    #[Computed]
    public function logLines(): array
    {
        $job = $this->logJob;
        if (! $job) {
            return [];
        }

        $upid = data_get($job->result, 'upid');
        $node = data_get($job->result, 'node') ?? data_get($job->payload, 'node');

        if (! $upid || ! $node) {
            return [];
        }

        try {
            return app(ProxmoxClient::class)->getTaskLog($node, $upid, start: 0, limit: 1000);
        } catch (\Throwable $e) {
            return [['n' => 0, 't' => '(failed to load log: '.$e->getMessage().')']];
        }
    }

    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.vm.section.table');
    }
}
