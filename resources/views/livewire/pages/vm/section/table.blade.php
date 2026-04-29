<div @if ($this->hasPendingActions) wire:poll.4s @endif>
    {{-- Sync info bar --}}
    <div class="mb-3 flex items-center justify-between text-xs text-gray-500 dark:text-neutral-400">
        <div class="flex items-center gap-3">
            @if ($this->lastSyncedAt)
                <span><x-lucide-clock class="size-3 inline" /> Last sync: {{ $this->lastSyncedAt }}</span>
            @else
                <span class="text-yellow-600">Belum pernah di-sync. Klik "Sync Sekarang".</span>
            @endif

            @php $sc = $this->statusCounts; @endphp
            @if (! empty($sc))
                <span>·</span>
                @if (($sc['running'] ?? 0) > 0)
                    <span class="text-green-600">{{ $sc['running'] }} running</span>
                @endif
                @if (($sc['stopped'] ?? 0) > 0)
                    <span class="text-gray-500">{{ $sc['stopped'] }} stopped</span>
                @endif
                @if (($sc['paused'] ?? 0) > 0)
                    <span class="text-yellow-600">{{ $sc['paused'] }} paused</span>
                @endif
            @endif
        </div>
        <a href="{{ url('admin/sync/jobs?service=proxmox') }}" wire:navigate class="text-blue-600 hover:underline">
            Lihat Sync Jobs →
        </a>
    </div>

    <x-nawasara-ui::filter-bar searchPlaceholder="Cari nama, vmid, deskripsi..." searchModel="search">
        <x-nawasara-ui::filter-dropdown label="Node" model="nodeFilter" :items="$this->nodeOptions" />
        <x-nawasara-ui::filter-dropdown label="Status" model="statusFilter"
            :items="['all' => 'Semua Status', 'running' => 'Running', 'stopped' => 'Stopped', 'paused' => 'Paused']" />
        <x-nawasara-ui::filter-dropdown label="Type" model="typeFilter"
            :items="['all' => 'VM + Container', 'qemu' => 'VM (qemu)', 'lxc' => 'Container (LXC)']" />
        <x-nawasara-ui::filter-dropdown label="Template" model="templateFilter"
            :items="['hide' => 'Sembunyikan template', 'only' => 'Hanya template', 'all' => 'Tampilkan semua']" />

        <x-slot:actions>
            <x-nawasara-ui::button color="neutral" variant="outline" size="sm" wire:click="refresh">
                <x-slot:icon>
                    <x-lucide-refresh-cw wire:loading.class="animate-spin" wire:target="refresh" />
                </x-slot:icon>
                Sync Sekarang
            </x-nawasara-ui::button>
        </x-slot:actions>

        <x-slot:chips>
            @if ($nodeFilter)
                <x-nawasara-ui::filter-chip label="Node: {{ $nodeFilter }}" model="nodeFilter" />
            @endif
            @if ($statusFilter)
                <x-nawasara-ui::filter-chip label="Status: {{ ucfirst($statusFilter) }}" model="statusFilter" />
            @endif
            @if ($typeFilter)
                <x-nawasara-ui::filter-chip label="Type: {{ $typeFilter }}" model="typeFilter" />
            @endif
            @if ($search)
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table
        :headers="['VMID', 'Name', 'Type', 'Node', 'Status', 'CPU', 'Memory', 'Disk', 'Uptime', '']"
        :title="'VMs ('.$this->vms->total().' total)'">
        <x-slot:table>
            @forelse ($this->vms as $vm)
                <tr wire:key="vm-{{ $vm->id }}">
                    <td class="px-4 py-3 whitespace-nowrap text-xs font-mono text-gray-600 dark:text-neutral-400">
                        {{ $vm->vmid }}
                    </td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-neutral-200">
                        <div class="flex items-center gap-2">
                            <span>{{ $vm->name ?? '—' }}</span>
                            @if ($vm->template)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400 uppercase">template</span>
                            @endif
                            @if ($vm->isLocked())
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400 uppercase" title="Locked: {{ $vm->lock }}">
                                    {{ $vm->lock }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs">
                        @if ($vm->vm_type === 'qemu')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">VM</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">LXC</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs font-mono text-gray-600 dark:text-neutral-400">
                        {{ $vm->node_name }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                        @php $lc = $this->lifecycleJobs[$vm->id] ?? null; @endphp
                        @if ($lc && in_array($lc->status, ['queued', 'running']))
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                <x-lucide-loader-circle class="size-3 animate-spin" />
                                {{ ucfirst(str_replace('vm_', '', $lc->action)) }}…
                            </span>
                        @elseif ($lc && $lc->status === 'failed' && $lc->finished_at?->gt(now()->subMinutes(5)))
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400" title="{{ $lc->error }}">
                                <x-lucide-circle-alert class="size-3" />
                                {{ ucfirst(str_replace('vm_', '', $lc->action)) }} failed
                            </span>
                        @elseif ($vm->status === 'running')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                <span class="size-1.5 rounded-full bg-green-500"></span> Running
                            </span>
                        @elseif ($vm->status === 'stopped')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400">
                                Stopped
                            </span>
                        @elseif ($vm->status === 'paused')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                Paused
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400">
                                {{ ucfirst($vm->status ?? 'unknown') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600 dark:text-neutral-400 font-mono">
                        {{ $vm->cpu_count ?? '—' }}
                        @if ($vm->cpu_usage !== null && $vm->isRunning())
                            <span class="text-gray-400">({{ round($vm->cpu_usage * 100, 1) }}%)</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600 dark:text-neutral-400 font-mono">
                        @if ($vm->mem_total)
                            {{ number_format($vm->mem_total / 1024 / 1024 / 1024, 1) }}G
                            @if ($vm->isRunning() && $vm->memUsagePercent() !== null)
                                <span class="text-gray-400">({{ $vm->memUsagePercent() }}%)</span>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600 dark:text-neutral-400 font-mono">
                        @if ($vm->disk_total)
                            {{ number_format($vm->disk_total / 1024 / 1024 / 1024, 0) }}G
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-neutral-500">
                        @if ($vm->uptime && $vm->isRunning())
                            @php
                                $d = floor($vm->uptime / 86400);
                                $h = floor(($vm->uptime % 86400) / 3600);
                            @endphp
                            {{ $d > 0 ? $d.'d ' : '' }}{{ $h }}h
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                        @php
                            $items = [
                                ['type' => 'click', 'label' => 'Detail', 'wire:click' => 'openDetail('.$vm->id.')', 'modal' => 'proxmox-vm-detail', 'icon' => 'lucide-eye', 'permission' => 'proxmox.vm.view'],
                            ];
                            if (! $vm->template) {
                                if ($vm->status !== 'running') {
                                    $items[] = ['type' => 'click', 'label' => 'Start', 'wire:click' => "vmAction({$vm->id}, 'start')", 'icon' => 'lucide-play', 'permission' => 'proxmox.vm.lifecycle', 'confirm' => "Start VM {$vm->name} (#{$vm->vmid})?"];
                                }
                                if ($vm->status === 'running') {
                                    $items[] = ['type' => 'click', 'label' => 'Restart', 'wire:click' => "vmAction({$vm->id}, 'restart')", 'icon' => 'lucide-rotate-cw', 'permission' => 'proxmox.vm.lifecycle', 'confirm' => "Reboot VM {$vm->name} (#{$vm->vmid})?\n\nGuest agent atau init container harus aktif untuk reboot graceful."];
                                    $items[] = ['type' => 'click', 'label' => 'Shutdown', 'wire:click' => "vmAction({$vm->id}, 'shutdown')", 'icon' => 'lucide-power', 'permission' => 'proxmox.vm.lifecycle', 'confirm' => "Graceful shutdown VM {$vm->name} (#{$vm->vmid})?\n\nSama seperti tekan tombol power di komputer fisik."];
                                    $items[] = ['type' => 'click', 'label' => 'Stop (force)', 'wire:click' => "vmAction({$vm->id}, 'stop')", 'icon' => 'lucide-square', 'permission' => 'proxmox.vm.lifecycle', 'confirm' => "FORCE STOP VM {$vm->name} (#{$vm->vmid})?\n\nIni akan memutus power tanpa shutdown graceful — data yg belum di-flush bisa hilang. Pakai opsi Shutdown jika memungkinkan."];
                                    if (! empty($this->consoleUrls[$vm->id])) {
                                        $items[] = ['type' => 'href', 'label' => 'Buka Console', 'href' => $this->consoleUrls[$vm->id], 'target' => '_blank', 'icon' => 'lucide-terminal', 'permission' => 'proxmox.vm.console'];
                                    }
                                }
                                if ($lc) {
                                    $items[] = ['type' => 'click', 'label' => 'Lihat Log', 'wire:click' => "openLog({$vm->id})", 'modal' => 'proxmox-vm-log', 'icon' => 'lucide-scroll-text', 'permission' => 'proxmox.vm.view'];
                                }
                            }
                        @endphp
                        <x-nawasara-ui::dropdown-menu-action :id="$vm->id" :items="$items" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        @if ($this->lastSyncedAt === null)
                            Database masih kosong. Klik <strong>Sync Sekarang</strong> untuk fetch dari Proxmox.
                        @else
                            Tidak ada VM yang cocok filter ini.
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->vms->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>

    {{-- Detail Modal --}}
    <x-nawasara-ui::modal id="proxmox-vm-detail" maxWidth="2xl" :title="$this->detail ? ($this->detail->name ?? 'VM '.$this->detail->vmid) : ''">
        @if ($this->detail)
            @php
                $v = $this->detail;
                $statusColor = match ($v->status) {
                    'running' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                    'stopped' => 'bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400',
                    'paused' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                    default => 'bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400',
                };
            @endphp

            {{-- Header summary --}}
            <div class="mb-4 flex flex-wrap items-center gap-2">
                @if ($v->vm_type === 'qemu')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">VM</span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">LXC</span>
                @endif
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                    @if ($v->status === 'running')
                        <span class="size-1.5 rounded-full bg-green-500 mr-1"></span>
                    @endif
                    {{ ucfirst($v->status ?? 'unknown') }}
                </span>
                @if ($v->template)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400 uppercase">template</span>
                @endif
                @if ($v->isLocked())
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400 uppercase">
                        Locked: {{ $v->lock }}
                    </span>
                @endif
            </div>

            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div class="flex justify-between gap-4 border-b border-gray-100 dark:border-neutral-800 pb-2">
                    <dt class="text-gray-500 dark:text-neutral-400">VMID</dt>
                    <dd class="font-mono text-gray-900 dark:text-neutral-100">{{ $v->vmid }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-b border-gray-100 dark:border-neutral-800 pb-2">
                    <dt class="text-gray-500 dark:text-neutral-400">Type</dt>
                    <dd class="text-gray-900 dark:text-neutral-100">{{ $v->vm_type === 'qemu' ? 'Virtual Machine' : 'LXC Container' }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-b border-gray-100 dark:border-neutral-800 pb-2">
                    <dt class="text-gray-500 dark:text-neutral-400">Node</dt>
                    <dd class="font-mono text-gray-900 dark:text-neutral-100">{{ $v->node_name }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-b border-gray-100 dark:border-neutral-800 pb-2">
                    <dt class="text-gray-500 dark:text-neutral-400">vCPU</dt>
                    <dd class="font-mono text-gray-900 dark:text-neutral-100">
                        {{ $v->cpu_count ?? '—' }}
                        @if ($v->cpu_usage !== null && $v->isRunning())
                            <span class="text-gray-400 dark:text-neutral-500">({{ round($v->cpu_usage * 100, 1) }}%)</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4 border-b border-gray-100 dark:border-neutral-800 pb-2">
                    <dt class="text-gray-500 dark:text-neutral-400">Memory total</dt>
                    <dd class="font-mono text-gray-900 dark:text-neutral-100">
                        {{ $v->mem_total ? number_format($v->mem_total / 1024 / 1024 / 1024, 2).' GB' : '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4 border-b border-gray-100 dark:border-neutral-800 pb-2">
                    <dt class="text-gray-500 dark:text-neutral-400">Memory used</dt>
                    <dd class="font-mono text-gray-900 dark:text-neutral-100">
                        {{ $v->mem_used ? number_format($v->mem_used / 1024 / 1024 / 1024, 2).' GB' : '—' }}
                        @if ($v->memUsagePercent() !== null)
                            <span class="text-gray-400 dark:text-neutral-500">({{ $v->memUsagePercent() }}%)</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-4 border-b border-gray-100 dark:border-neutral-800 pb-2">
                    <dt class="text-gray-500 dark:text-neutral-400">Disk total</dt>
                    <dd class="font-mono text-gray-900 dark:text-neutral-100">
                        {{ $v->disk_total ? number_format($v->disk_total / 1024 / 1024 / 1024, 0).' GB' : '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4 border-b border-gray-100 dark:border-neutral-800 pb-2">
                    <dt class="text-gray-500 dark:text-neutral-400">Uptime</dt>
                    <dd class="font-mono text-gray-900 dark:text-neutral-100">
                        @if ($v->uptime && $v->isRunning())
                            @php
                                $d = floor($v->uptime / 86400);
                                $h = floor(($v->uptime % 86400) / 3600);
                                $m = floor(($v->uptime % 3600) / 60);
                            @endphp
                            {{ $d > 0 ? $d.'d ' : '' }}{{ $h }}h {{ $m }}m
                        @else
                            —
                        @endif
                    </dd>
                </div>

                @if (! empty($v->tags))
                    <div class="col-span-2 pt-1">
                        <dt class="text-gray-500 dark:text-neutral-400 mb-1">Tags</dt>
                        <dd class="flex flex-wrap gap-1">
                            @foreach ($v->tags as $tag)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300">{{ $tag }}</span>
                            @endforeach
                        </dd>
                    </div>
                @endif
            </dl>

            {{-- Live config from Proxmox API (network, disks, OS) --}}
            @php $cfg = $this->detailConfig; @endphp
            @if ($cfg)
                @if (! empty($cfg['networks']))
                    <div class="mt-5">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-neutral-400 mb-2">Network Interfaces</h4>
                        <div class="space-y-2">
                            @foreach ($cfg['networks'] as $net)
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs px-3 py-2 rounded border border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-800/40">
                                    <span class="font-mono font-semibold text-gray-700 dark:text-neutral-200">{{ $net['id'] }}</span>
                                    @foreach ($net['parts'] as $k => $val)
                                        <span><span class="text-gray-500 dark:text-neutral-500">{{ $k }}:</span> <span class="font-mono text-gray-700 dark:text-neutral-300">{{ $val }}</span></span>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($cfg['disks']))
                    <div class="mt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-neutral-400 mb-2">Disks</h4>
                        <div class="space-y-1">
                            @foreach ($cfg['disks'] as $disk)
                                <div class="flex items-baseline gap-3 text-xs px-3 py-1.5 rounded border border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-800/40">
                                    <span class="font-mono font-semibold text-gray-700 dark:text-neutral-200 shrink-0">{{ $disk['id'] }}</span>
                                    <span class="font-mono text-gray-600 dark:text-neutral-400 truncate" title="{{ $disk['raw'] }}">{{ $disk['raw'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($cfg['description']))
                    <div class="mt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-neutral-400 mb-1">Description</h4>
                        <p class="text-sm text-gray-700 dark:text-neutral-300 whitespace-pre-wrap">{{ $cfg['description'] }}</p>
                    </div>
                @endif

                @if (! empty($cfg['os_type']) || ! empty($cfg['boot']))
                    <div class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-xs">
                        @if (! empty($cfg['os_type']))
                            <span><span class="text-gray-500 dark:text-neutral-500">OS type:</span> <span class="font-mono text-gray-700 dark:text-neutral-300">{{ $cfg['os_type'] }}</span></span>
                        @endif
                        @if (! empty($cfg['boot']))
                            <span><span class="text-gray-500 dark:text-neutral-500">Boot:</span> <span class="font-mono text-gray-700 dark:text-neutral-300">{{ $cfg['boot'] }}</span></span>
                        @endif
                    </div>
                @endif
            @endif

            <div class="mt-4 pt-3 border-t border-gray-200 dark:border-neutral-700 text-xs text-gray-500 dark:text-neutral-400">
                Last synced: {{ $v->last_synced_at?->diffForHumans() ?? '—' }}
            </div>
        @endif
        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline" wire:click="closeDetail">Tutup</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>

    {{-- Task Log Modal --}}
    <x-nawasara-ui::modal id="proxmox-vm-log" maxWidth="3xl"
        :title="$this->logJob ? 'Log: '.str_replace('vm_', '', $this->logJob->action).' — '.$this->logJob->target_id : 'Task Log'">
        @if ($this->logJob)
            @php $j = $this->logJob; @endphp

            {{-- Header status --}}
            <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
                @php
                    $statusBadge = match ($j->status) {
                        'queued' => 'bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-300',
                        'running' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                        'success' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                        'failed' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                        'conflict' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                        default => 'bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400',
                    };
                @endphp
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-medium {{ $statusBadge }}">
                    @if ($j->status === 'running')
                        <x-lucide-loader-circle class="size-3 animate-spin" />
                    @endif
                    {{ ucfirst($j->status) }}
                </span>
                <span class="text-gray-500 dark:text-neutral-400">·</span>
                <span class="text-gray-600 dark:text-neutral-300">{{ $j->action }}</span>
                @if ($j->started_at)
                    <span class="text-gray-500 dark:text-neutral-400">· started {{ $j->started_at->diffForHumans() }}</span>
                @endif
                @if ($j->duration_ms)
                    <span class="text-gray-500 dark:text-neutral-400">· {{ number_format($j->duration_ms) }} ms</span>
                @endif
                @if ($upid = data_get($j->result, 'upid'))
                    <span class="text-gray-500 dark:text-neutral-400">·</span>
                    <span class="font-mono text-[11px] text-gray-500 dark:text-neutral-400" title="{{ $upid }}">UPID {{ \Illuminate\Support\Str::limit($upid, 36) }}</span>
                @endif
            </div>

            @if ($j->error)
                <div class="mb-3 px-3 py-2 rounded border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-900/20 text-xs text-red-700 dark:text-red-400">
                    <strong>Error:</strong> {{ $j->error }}
                </div>
            @endif

            {{-- Log lines from Proxmox task log --}}
            <div class="rounded-lg border border-gray-200 dark:border-neutral-700 bg-gray-900 dark:bg-black/40 max-h-96 overflow-auto">
                <pre class="px-4 py-3 text-[11px] leading-relaxed font-mono text-green-300 whitespace-pre-wrap break-all">@forelse ($this->logLines as $line)<span class="text-neutral-500">{{ str_pad((string) ($line['n'] ?? ''), 4, ' ', STR_PAD_LEFT) }}</span>  {{ $line['t'] ?? '' }}
@empty
<span class="text-neutral-500">(belum ada log — task mungkin masih queued, atau UPID tidak tersedia.)</span>
@endforelse</pre>
            </div>
        @endif
        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline" wire:click="closeLog">Tutup</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
