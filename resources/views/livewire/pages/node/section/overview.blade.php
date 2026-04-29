<div wire:poll.{{ $this->isSyncing ? '4s' : '30s' }}>
    {{-- Sync info bar --}}
    <div class="mb-3 flex items-center justify-between text-xs text-gray-500 dark:text-neutral-400">
        <div class="flex items-center gap-3">
            @if ($this->lastSyncedAt)
                <span><x-lucide-clock class="size-3 inline" /> Last sync: {{ $this->lastSyncedAt }}</span>
            @else
                <span class="text-yellow-600">Belum pernah di-sync.</span>
            @endif
        </div>
        <x-nawasara-ui::button color="neutral" variant="outline" size="sm" wire:click="refresh">
            <x-slot:icon>
                <x-lucide-refresh-cw wire:loading.class="animate-spin" wire:target="refresh" />
            </x-slot:icon>
            Sync Sekarang
        </x-nawasara-ui::button>
    </div>

    {{-- Cluster totals --}}
    @php $t = $this->clusterTotals; @endphp
    <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-3">Cluster Summary</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="p-4 rounded-xl border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800">
            <div class="flex items-center gap-2 text-xs uppercase text-gray-500 dark:text-neutral-400">
                <x-lucide-cpu class="size-4" /> Nodes
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                {{ $t['nodes_online'] }}<span class="text-sm text-gray-400 font-normal">/{{ $t['nodes'] }}</span>
            </div>
            <div class="text-xs text-gray-500 dark:text-neutral-400">online</div>
        </div>
        <div class="p-4 rounded-xl border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800">
            <div class="flex items-center gap-2 text-xs uppercase text-gray-500 dark:text-neutral-400">
                <x-lucide-monitor class="size-4" /> Virtual Machines
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                {{ $t['vm_running'] }}<span class="text-sm text-gray-400 font-normal">/{{ $t['vm_count'] }}</span>
            </div>
            <div class="text-xs text-gray-500 dark:text-neutral-400">running</div>
        </div>
        <div class="p-4 rounded-xl border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800">
            <div class="flex items-center gap-2 text-xs uppercase text-gray-500 dark:text-neutral-400">
                <x-lucide-microchip class="size-4" /> Total vCPU
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($t['cpu_total']) }}</div>
            <div class="text-xs text-gray-500 dark:text-neutral-400">cores</div>
        </div>
        <div class="p-4 rounded-xl border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800">
            <div class="flex items-center gap-2 text-xs uppercase text-gray-500 dark:text-neutral-400">
                <x-lucide-memory-stick class="size-4" /> Memory
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                {{ $t['mem_total'] ? number_format($t['mem_total'] / 1024 / 1024 / 1024, 0) : '—' }}
                <span class="text-sm text-gray-400 font-normal">GB</span>
            </div>
            @if ($t['mem_total'])
                <div class="text-xs text-gray-500 dark:text-neutral-400">
                    {{ round(($t['mem_used'] / $t['mem_total']) * 100, 1) }}% used
                </div>
            @endif
        </div>
        <div class="p-4 rounded-xl border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800">
            <div class="flex items-center gap-2 text-xs uppercase text-gray-500 dark:text-neutral-400">
                <x-lucide-hard-drive class="size-4" /> Storage
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                {{ $t['disk_total'] ? number_format($t['disk_total'] / 1024 / 1024 / 1024 / 1024, 1) : '—' }}
                <span class="text-sm text-gray-400 font-normal">TB</span>
            </div>
            @if ($t['disk_total'])
                <div class="text-xs text-gray-500 dark:text-neutral-400">
                    {{ round(($t['disk_used'] / $t['disk_total']) * 100, 1) }}% used
                </div>
            @endif
        </div>
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
                        class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                        Lihat VM di node ini →
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12 border-2 border-dashed border-gray-200 dark:border-neutral-700 rounded-xl">
                <x-lucide-server class="size-12 mx-auto text-gray-300 dark:text-neutral-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-neutral-400">Belum ada data node. Klik <strong>Sync Sekarang</strong>.</p>
            </div>
        @endforelse
    </div>
</div>
