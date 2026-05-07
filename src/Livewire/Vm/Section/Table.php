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
use Nawasara\Ui\Livewire\Concerns\HasArrayFilters;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;
use Nawasara\Ui\Livewire\Concerns\HasExport;

class Table extends Component
{
    use HasArrayFilters;
    use HasBrowserToast;
    use HasExport;
    use WithPagination;

    /**
     * Multi-select filters using filter-panel array semantics.
     * Empty array == no filter; the model scopes accept either string or
     * array via polymorphic signature.
     *
     * NOTE: type hint dropped from `array` to untyped because legacy
     * bookmarks like `?nodeFilter=pve-2` would hydrate as string and
     * crash a typed array property. HasArrayFilters trait coerces the
     * scalar to a single-element array at boot time. PHPDoc preserves
     * the intent for IDEs and code review.
     *
     * @var array<int, string>
     */
    #[Url]
    public $nodeFilter = [];

    /** @var array<int, string> */
    #[Url]
    public $statusFilter = [];

    /** @var array<int, string> */
    #[Url]
    public $typeFilter = [];

    /**
     * Template visibility — single-select tri-state ('hide' / 'only' /
     * 'all'). Stays scalar because the underlying SQL is exclusive
     * (template=true OR template=false OR no constraint).
     */
    #[Url(except: 'hide')]
    public string $templateFilter = 'hide'; // default sembunyikan template

    public string $search = '';
    public int $perPage = 50;

    // Detail modal
    public ?int $detailId = null;

    // Log modal
    public ?int $logSyncJobId = null;

    // Snapshot create form
    public string $snapName = '';
    public string $snapDescription = '';
    public bool $snapIncludeRam = false;
    public bool $showSnapForm = false;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedNodeFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }
    public function updatedTemplateFilter(): void { $this->resetPage(); }

    protected function repo(): ProxmoxVmRepository
    {
        return new ProxmoxVmRepository();
    }

    /**
     * Filters that may receive scalar values from legacy bookmarks
     * (`?nodeFilter=pve-2`). HasArrayFilters wraps any scalar into a
     * single-element array before computed properties read them.
     */
    protected function arrayFilters(): array
    {
        return ['nodeFilter', 'statusFilter', 'typeFilter'];
    }

    #[Computed]
    public function vms()
    {
        return $this->repo()->list([
            'search' => $this->search ?: null,
            // Empty arrays pass through; polymorphic scopes are no-op on empty.
            'node' => $this->nodeFilter,
            'status' => $this->statusFilter,
            'type' => $this->typeFilter,
            'template' => $this->templateFilter ?: null,
        ], $this->perPage);
    }

    /**
     * Node names for the filter-panel "Node" dimension. No 'all' sentinel
     * here — filter-panel uses empty array == no filter. Each node maps
     * to itself (key == label) since node_name is short and explicit.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function nodeOptions(): array
    {
        $nodes = ProxmoxNode::orderBy('node_name')->pluck('node_name')->all();
        $opts = [];
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
        $this->showSnapForm = false;
        $this->resetSnapForm();
        $this->dispatch('modal-open:proxmox-vm-detail');
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
        $this->showSnapForm = false;
        $this->resetSnapForm();
        $this->dispatch('modal-close:proxmox-vm-detail');
    }

    protected function resetSnapForm(): void
    {
        $this->snapName = '';
        $this->snapDescription = '';
        $this->snapIncludeRam = false;
    }

    public function toggleSnapForm(): void
    {
        $this->showSnapForm = ! $this->showSnapForm;
        if (! $this->showSnapForm) {
            $this->resetSnapForm();
        }
    }

    public function createSnapshot(): void
    {
        Gate::authorize('proxmox.vm.snapshot');

        $vm = $this->detail;
        if (! $vm) {
            $this->toastError('VM tidak ditemukan.');
            return;
        }

        $name = trim($this->snapName);
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{1,39}$/', $name)) {
            $this->toastError('Nama snapshot harus 2–40 karakter, mulai huruf, hanya alfanumerik / underscore.');
            return;
        }

        try {
            $this->repo()->createSnapshot($vm, $name, trim($this->snapDescription), $this->snapIncludeRam);
        } catch (\Throwable $e) {
            $this->toastError('Gagal dispatch snapshot: '.$e->getMessage());
            return;
        }

        $this->toastSuccess("Snapshot '{$name}' sedang dibuat untuk VM #{$vm->vmid}.");
        $this->showSnapForm = false;
        $this->resetSnapForm();
        unset($this->detailSnapshots);
    }

    public function rollbackSnapshot(string $snapName): void
    {
        Gate::authorize('proxmox.vm.snapshot');

        $vm = $this->detail;
        if (! $vm) {
            $this->toastError('VM tidak ditemukan.');
            return;
        }

        try {
            $this->repo()->rollbackSnapshot($vm, $snapName);
        } catch (\Throwable $e) {
            $this->toastError('Gagal rollback: '.$e->getMessage());
            return;
        }

        $this->toastSuccess("Rollback ke snapshot '{$snapName}' sedang berjalan.");
    }

    public function deleteSnapshot(string $snapName): void
    {
        Gate::authorize('proxmox.vm.snapshot');

        $vm = $this->detail;
        if (! $vm) {
            $this->toastError('VM tidak ditemukan.');
            return;
        }

        try {
            $this->repo()->deleteSnapshot($vm, $snapName);
        } catch (\Throwable $e) {
            $this->toastError('Gagal hapus snapshot: '.$e->getMessage());
            return;
        }

        $this->toastSuccess("Snapshot '{$snapName}' sedang dihapus.");
        unset($this->detailSnapshots);
    }

    /**
     * Snapshot list for the open detail modal. Filters out the synthetic
     * "current" entry that Proxmox always returns at the end.
     */
    #[Computed]
    public function detailSnapshots(): array
    {
        $vm = $this->detail;
        if (! $vm) {
            return [];
        }

        try {
            $rows = app(ProxmoxClient::class)->getSnapshots(
                $vm->node_name, (int) $vm->vmid, $vm->vm_type
            );
        } catch (\Throwable) {
            return [];
        }

        // Sort newest first; drop "current"
        $rows = array_filter($rows, fn ($r) => ($r['name'] ?? '') !== 'current');
        usort($rows, fn ($a, $b) => ($b['snaptime'] ?? 0) <=> ($a['snaptime'] ?? 0));

        return array_values($rows);
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

    /**
     * Export filename base — timestamp + extension appended by HasExport.
     */
    protected function exportFilename(): string
    {
        return 'proxmox-vms';
    }

    /**
     * Export FULL VM inventory (no filter) per spec. Includes node + type
     * + status + resources so an offline review can spot capacity issues
     * without re-querying. Templates included with explicit flag column.
     */
    protected function exportData(): iterable
    {
        return ProxmoxVm::query()
            ->orderBy('node_name')
            ->orderBy('vmid')
            ->get()
            ->map(fn (ProxmoxVm $vm) => [
                'VMID' => $vm->vmid,
                'Name' => $vm->name,
                'Type' => $vm->vm_type,
                'Node' => $vm->node_name,
                'Status' => $vm->status,
                'Template' => $vm->template ? 'Yes' : 'No',
                'CPU Cores' => $vm->cpu_count,
                'CPU Usage %' => $vm->cpu_usage !== null ? round($vm->cpu_usage * 100, 2) : null,
                'Memory MB' => $vm->mem_total ? round($vm->mem_total / 1024 / 1024) : null,
                'Memory Used MB' => $vm->mem_used ? round($vm->mem_used / 1024 / 1024) : null,
                'Disk GB' => $vm->disk_total ? round($vm->disk_total / 1024 / 1024 / 1024, 1) : null,
                'Disk Used GB' => $vm->disk_used ? round($vm->disk_used / 1024 / 1024 / 1024, 1) : null,
                'IP Addresses' => is_array($vm->ip_addresses) ? implode(', ', $vm->ip_addresses) : (string) $vm->ip_addresses,
                'Tags' => is_array($vm->tags) ? implode(', ', $vm->tags) : (string) $vm->tags,
                'Locked' => $vm->lock ?: '',
                'Description' => $vm->description,
                'Last Synced' => optional($vm->last_synced_at)->format('Y-m-d H:i'),
            ]);
    }

    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.vm.section.table');
    }
}
