<div wire:poll.{{ $this->isSyncing ? '4s' : '30s' }}>
    {{-- Sync info bar --}}
    <div class="mb-3 flex items-center justify-between text-xs text-gray-500 dark:text-neutral-400">
        <div class="flex items-center gap-3">
            @if ($this->lastSyncedAt)
                <span><x-lucide-clock class="size-3 inline" /> Last sync: {{ $this->lastSyncedAt }}</span>
            @else
                <span class="text-amber-700 dark:text-amber-400">Belum pernah di-sync.</span>
            @endif
        </div>
        <x-nawasara-ui::button color="neutral" variant="outline" size="sm" wire:click="refresh">
            <x-slot:icon>
                <x-lucide-refresh-cw wire:loading.class="animate-spin" wire:target="refresh" />
            </x-slot:icon>
            Sync Sekarang
        </x-nawasara-ui::button>
    </div>

    {{-- Cluster totals — using design-system stat-card.
         Color logic:
         - Nodes: success kalau semua online, warning kalau ada offline
         - VMs: primary (informational)
         - vCPU/Memory/Storage: neutral (capacity, bukan health) --}}
    @php
        $t = $this->clusterTotals;
        $nodesAllOnline = $t['nodes'] > 0 && $t['nodes_online'] === $t['nodes'];
        $memPct = $t['mem_total'] ? round(($t['mem_used'] / $t['mem_total']) * 100, 1) : null;
        $diskPct = $t['disk_total'] ? round(($t['disk_used'] / $t['disk_total']) * 100, 1) : null;

        // Pick danger/warning/neutral color for capacity stats based on usage %.
        $memColor = $memPct === null ? 'neutral' : ($memPct >= 90 ? 'danger' : ($memPct >= 75 ? 'warning' : 'neutral'));
        $diskColor = $diskPct === null ? 'neutral' : ($diskPct >= 90 ? 'danger' : ($diskPct >= 75 ? 'warning' : 'neutral'));
    @endphp
    <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-3">Cluster Summary</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
        <x-nawasara-ui::stat-card
            label="Nodes"
            :value="$t['nodes_online'].' / '.$t['nodes']"
            icon="lucide-cpu"
            :color="$nodesAllOnline ? 'success' : ($t['nodes_online'] === 0 ? 'danger' : 'warning')"
            description="online"
            accent />

        <x-nawasara-ui::stat-card
            label="Virtual Machines"
            :value="$t['vm_running'].' / '.$t['vm_count']"
            icon="lucide-monitor"
            color="primary"
            description="running"
            accent />

        <x-nawasara-ui::stat-card
            label="Total vCPU"
            :value="number_format($t['cpu_total'])"
            icon="lucide-microchip"
            color="neutral"
            description="cores"
            accent />

        <x-nawasara-ui::stat-card
            label="Memory"
            :value="$t['mem_total'] ? number_format($t['mem_total'] / 1024 / 1024 / 1024, 0).' GB' : '—'"
            icon="lucide-memory-stick"
            :color="$memColor"
            :description="$memPct !== null ? $memPct.'% used' : null"
            accent />

        <x-nawasara-ui::stat-card
            label="Storage"
            :value="$t['disk_total'] ? number_format($t['disk_total'] / 1024 / 1024 / 1024 / 1024, 1).' TB' : '—'"
            icon="lucide-hard-drive"
            :color="$diskColor"
            :description="$diskPct !== null ? $diskPct.'% used' : null"
            accent />
    </div>

    {{-- Per-node detail --}}
    <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-3">Per-Node Detail</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($this->nodes as $node)
            <div class="p-5 rounded-xl border {{ $node->status === 'online' ? 'border-green-200 dark:border-green-900 bg-green-50/30 dark:bg-green-900/10' : 'border-red-200 dark:border-red-900 bg-red-50/30 dark:bg-red-900/10' }}">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <x-lucide-server class="size-5 text-gray-500" />
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $node->node_name }}</h3>
                    </div>
                    @if ($node->status === 'online')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                            <span class="size-1.5 rounded-full bg-green-500"></span> Online
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                            {{ ucfirst($node->status) }}
                        </span>
                    @endif
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-neutral-400">vCPU</span>
                        <span class="font-mono text-gray-700 dark:text-neutral-300">
                            {{ $node->cpu_count ?? '—' }}
                            @if ($node->cpu_usage !== null && $node->status === 'online')
                                <span class="text-gray-400">({{ round($node->cpu_usage * 100, 1) }}%)</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-neutral-400">Memory</span>
                        <span class="font-mono text-gray-700 dark:text-neutral-300">
                            {{ $node->mem_total ? number_format($node->mem_total / 1024 / 1024 / 1024, 0).' GB' : '—' }}
                            @if ($node->memUsagePercent() !== null)
                                <span class="text-gray-400">({{ $node->memUsagePercent() }}%)</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-neutral-400">Storage</span>
                        <span class="font-mono text-gray-700 dark:text-neutral-300">
                            {{ $node->disk_total ? number_format($node->disk_total / 1024 / 1024 / 1024, 0).' GB' : '—' }}
                            @if ($node->diskUsagePercent() !== null)
                                <span class="text-gray-400">({{ $node->diskUsagePercent() }}%)</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-neutral-400">Uptime</span>
                        <span class="font-mono text-gray-700 dark:text-neutral-300">
                            @if ($node->uptime)
                                @php
                                    $d = floor($node->uptime / 86400);
                                    $h = floor(($node->uptime % 86400) / 3600);
                                @endphp
                                {{ $d }}d {{ $h }}h
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    @if ($node->kernel_version)
                        <div class="text-xs text-gray-500 dark:text-neutral-500 pt-2 border-t border-gray-200 dark:border-neutral-700 truncate" title="{{ $node->kernel_version }}">
                            {{ $node->kernel_version }}
                        </div>
                    @endif
                </div>

                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-neutral-700">
                    <a href="{{ url('nawasara-proxmox/vms?nodeFilter='.$node->node_name) }}" wire:navigate
                        class="text-xs text-emerald-700 dark:text-emerald-400 hover:underline font-medium">
                        Lihat VM di node ini →
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-16 px-6 border-2 border-dashed border-gray-200 dark:border-neutral-700 rounded-xl bg-gray-50/50 dark:bg-neutral-900/40">
                <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-gray-100 dark:bg-neutral-800 mb-4">
                    <x-lucide-server class="size-7 text-gray-400 dark:text-neutral-500" />
                </div>
                <p class="text-base font-semibold text-gray-800 dark:text-neutral-200">
                    Belum ada data node
                </p>
                <p class="mt-2 text-sm text-gray-500 dark:text-neutral-400 max-w-sm mx-auto">
                    Klik <strong class="text-gray-700 dark:text-neutral-300">Sync Sekarang</strong> di atas untuk fetch cluster state dari Proxmox.
                </p>
            </div>
        @endforelse
    </div>
</div>
